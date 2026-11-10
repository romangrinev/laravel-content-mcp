<?php

namespace GrinevStudio\LaravelContentMcp\Media;

use GrinevStudio\LaravelContentMcp\Contracts\MediaAdapter;
use GrinevStudio\LaravelContentMcp\Exceptions\ContentMcpException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Throwable;

class MediaManager
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function upload(?Authenticatable $actor, array $input): array
    {
        return $this->execute($actor, 'upload', $input);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function uploadStatus(?Authenticatable $actor, array $input): array
    {
        return $this->execute($actor, 'status', $input);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function get(?Authenticatable $actor, array $input): array
    {
        return $this->execute($actor, 'get', $input);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(?Authenticatable $actor, array $input): array
    {
        return $this->execute($actor, 'update', $input);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function attach(?Authenticatable $actor, array $input): array
    {
        return $this->execute($actor, 'attach', $input);
    }

    private function adapter(): ?MediaAdapter
    {
        $class = config('content-mcp.media.adapter');

        return is_string($class) && is_subclass_of($class, MediaAdapter::class) ? app($class) : null;
    }

    public function supports(string $flag): bool
    {
        return ($this->adapter()?->capabilities()[$flag] ?? false) === true;
    }

    /** @return array<string, mixed>|null */
    public function capabilities(Authenticatable $actor): ?array
    {
        $adapter = $this->adapter();
        if (! $adapter || ! $adapter->authorize($actor, 'capabilities')) {
            return null;
        }

        return [...$adapter->capabilities(), 'can_get_usages' => false, 'can_delete' => false, 'can_import_url' => false];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(?Authenticatable $actor, string $operation, array $input): array
    {
        $actor ??= throw new ContentMcpException('authentication_required', 401);
        $flag = match ($operation) {
            'status' => 'can_upload',
            'get' => 'can_read',
            default => 'can_'.$operation,
        };
        $adapter = $this->adapter();
        if (! $adapter || ! $this->supports($flag)) {
            throw ContentMcpException::unsupported();
        }
        $ability = $operation === 'get' ? 'content:read' : 'content:write';
        if (method_exists($actor, 'currentAccessToken') && $actor->currentAccessToken() !== null && method_exists($actor, 'tokenCan') && ! $actor->tokenCan($ability)) {
            throw new ContentMcpException('permission_denied', 403);
        }
        if (! $adapter->authorize($actor, $operation === 'status' ? 'upload' : $operation, $input)) {
            throw new ContentMcpException('permission_denied', 403);
        }
        if ($operation === 'get') {
            return ['media' => $adapter->get($actor, $this->requiredString($input, 'media_id'))];
        }
        $key = $this->requiredString($input, 'idempotency_key');
        if (! preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', $key)) {
            throw new ContentMcpException('validation_failed', 422);
        }
        $kind = $operation === 'status' ? 'upload' : $operation;
        $id = hash('sha256', json_encode([$actor::class, (string) $actor->getAuthIdentifier(), $kind, $key], JSON_THROW_ON_ERROR));
        if ($operation === 'status') {
            return $this->receipt($adapter, $actor, $id) ?? ['status' => DB::table('content_mcp_media_operations')->where('operation_id', $id)->exists() ? 'uncertain' : 'not_found'];
        }
        $upload = $operation === 'upload' ? $this->decodeUpload($input, $adapter->capabilities()) : null;
        if ($operation !== 'upload') {
            $this->requiredString($input, 'version');
            $this->requiredString($input, $operation === 'update' ? 'media_id' : 'content_id');
            if ($operation === 'attach') {
                $this->requiredString($input, 'content_type');
            }
            if (! is_array($input['fields'] ?? null) || array_is_list($input['fields']) || strlen(json_encode($input['fields'], JSON_THROW_ON_ERROR)) > 100000) {
                throw new ContentMcpException('validation_failed', 422);
            }
        }
        unset($input['correlation_id']);
        $hash = hash('sha256', json_encode($this->canonical($input), JSON_THROW_ON_ERROR));
        // Commit the reservation BEFORE any filesystem side effect. No lease expires and
        // no timeout automatically retries storage. Host receipts repair uncertain outcomes.
        if (DB::transactionLevel() !== 0) {
            throw new ContentMcpException('upload_reconciliation_required', 409);
        }
        $claimed = DB::table('content_mcp_media_operations')->insertOrIgnore([
            'operation_id' => $id, 'operation' => $operation, 'request_hash' => $hash,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if (! $claimed) {
            $record = DB::table('content_mcp_media_operations')->where('operation_id', $id)->first();
            if (! $record || ! hash_equals($record->request_hash, $hash)) {
                throw new ContentMcpException('idempotency_conflict', 409);
            }
            $result = $this->receipt($adapter, $actor, $id);
            if ($result !== null) {
                return [...$result, 'idempotent_replay' => true];
            }
            throw new ContentMcpException('upload_reconciliation_required', 409);
        }
        try {
            $result = match ($operation) {
                'upload' => $adapter->upload($actor, $upload ?? throw new ContentMcpException('validation_failed', 422), $id),
                'update' => $adapter->update($actor, $input['media_id'], $input['version'], $input['fields'], $id),
                'attach' => $adapter->attach($actor, $input['content_type'], $input['content_id'], $input['version'], $input['fields'], $id),
                default => throw ContentMcpException::unsupported(),
            };
            $result = [...$result, 'status' => 'completed', 'idempotent_replay' => false];
            $this->save($id, $result);

            return $result;
        } catch (ContentMcpException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ContentMcpException('upload_reconciliation_required', 409);
        }
    }

    /** @return array<string, mixed>|null */
    private function receipt(MediaAdapter $adapter, Authenticatable $actor, string $id): ?array
    {
        $record = DB::table('content_mcp_media_operations')->where('operation_id', $id)->first();
        if (! $record) {
            return null;
        }
        if (is_string($record->result)) {
            return json_decode($record->result, true, 512, JSON_THROW_ON_ERROR);
        }
        $result = $adapter->operationResult($actor, $id);
        if ($result === null) {
            return null;
        }
        $result = [...$result, 'status' => 'completed'];
        $this->save($id, $result);

        return $result;
    }

    /** @param array<string, mixed> $result */
    private function save(string $id, array $result): void
    {
        DB::table('content_mcp_media_operations')->where('operation_id', $id)->update([
            'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $capabilities
     */
    private function decodeUpload(array $input, array $capabilities): MediaUpload
    {
        $max = min(8388608, max(1, (int) ($capabilities['max_upload_bytes'] ?? 8388608)));
        $data = $input['data_base64'] ?? null;
        if (! is_string($data) || strlen($data) > 4 * (int) ceil($max / 3)) {
            throw new ContentMcpException('validation_failed', 422);
        }
        $bytes = base64_decode($data, true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > $max || base64_encode($bytes) !== $data) {
            throw new ContentMcpException('validation_failed', 422);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $dimensions = @getimagesizefromstring($bytes);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || ! in_array($mime, $capabilities['allowed_mime_types'] ?? [], true)
            || $mime !== ($input['mime_type'] ?? null) || ! $dimensions
            || $dimensions[0] > min(10000, $capabilities['max_width'] ?? 10000)
            || $dimensions[1] > min(10000, $capabilities['max_height'] ?? 10000)
            || min(20000000, $capabilities['max_pixels'] ?? 20000000) < $dimensions[0] * $dimensions[1]) {
            throw new ContentMcpException('validation_failed', 422);
        }
        $metadata = $input['metadata'] ?? [];
        if (! is_array($metadata) || ($metadata !== [] && array_is_list($metadata))
            || array_diff(array_keys($metadata), $capabilities['metadata_fields'] ?? []) !== []
            || strlen(json_encode($metadata, JSON_THROW_ON_ERROR)) > 20000) {
            throw new ContentMcpException('validation_failed', 422);
        }

        return new MediaUpload($bytes, $this->requiredString($input, 'filename'), $metadata);
    }

    /** @param array<string, mixed> $input */
    private function requiredString(array $input, string $key): string
    {
        $value = $input[$key] ?? null;
        if (! is_string($value) || $value === '' || strlen($value) > 255) {
            throw new ContentMcpException('validation_failed', 422);
        }

        return $value;
    }

    /** @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private function canonical(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }
        unset($item);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
