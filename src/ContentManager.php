<?php

namespace GrinevStudio\LaravelContentMcp;

use Closure;
use GrinevStudio\LaravelContentMcp\Contracts\ContentResource;
use GrinevStudio\LaravelContentMcp\Exceptions\ContentMcpException;
use GrinevStudio\LaravelContentMcp\Models\IdempotencyKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContentManager
{
    public function __construct(private readonly ContentResourceRegistry $registry) {}

    /** @return array<string, mixed> */
    public function capabilities(?Authenticatable $actor, ?string $correlationId = null): array
    {
        $actor = $this->actor($actor);
        $this->ability($actor, 'content:read');

        $capabilities = [];
        foreach ($this->registry->all() as $resource) {
            if (! $resource->authorize($actor, 'read')) {
                continue;
            }
            $flags = $resource->capabilities();
            $capabilities[] = [
                'content_type' => $resource->contentType(),
                ...$flags,
                'operations' => array_map(
                    fn (string $key): string => str_replace('can_', '', $key),
                    array_keys(array_filter($flags)),
                ),
                'constraints' => $resource->constraints(),
            ];
        }

        return ['capabilities' => $capabilities, 'correlation_id' => $this->correlationId($correlationId)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function search(?Authenticatable $actor, array $input): array
    {
        $actor = $this->actor($actor);
        $this->ability($actor, 'content:read');
        $resource = $this->resource($input, 'can_list');
        $this->authorized($resource, $actor, 'list');
        $limit = min(max((int) ($input['limit'] ?? 20), 1), (int) config('content-mcp.limits.max_page_size', 50));
        $result = $resource->search($actor, $this->stringOrNull($input['query'] ?? null), $this->stringOrNull($input['status'] ?? null), $this->stringOrNull($input['cursor'] ?? null), $limit);

        return ['content_type' => $resource->contentType(), ...$result, 'correlation_id' => $this->correlationId($input['correlation_id'] ?? null)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function get(?Authenticatable $actor, array $input): array
    {
        $actor = $this->actor($actor);
        $this->ability($actor, 'content:read');
        $resource = $this->resource($input, 'can_read');
        $this->exactlyOneSelector($input);
        $item = $resource->find($actor, $this->stringOrNull($input['content_id'] ?? null), $this->stringOrNull($input['slug'] ?? null));
        if ($item === null) {
            throw new ContentMcpException('content_not_found', 404);
        }
        $this->authorized($resource, $actor, 'read', $item);

        return $this->itemResult($resource, $item, $input);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function createDraft(?Authenticatable $actor, array $input): array
    {
        return $this->mutate($actor, $input, 'create_draft', 'content:write', 'can_create_draft', function (ContentResource $resource, Authenticatable $actor, array $data): mixed {
            $fields = $this->validatedFields($resource, 'create_draft', $data['fields'] ?? []);

            return $resource->createDraft($actor, $fields);
        });
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(?Authenticatable $actor, array $input): array
    {
        return $this->mutate($actor, $input, 'update', 'content:write', 'can_update', function (ContentResource $resource, Authenticatable $actor, array $data): mixed {
            $item = $this->existingVersionedItem($resource, $actor, $data);
            $this->authorized($resource, $actor, 'update', $item);
            $fields = $this->validatedFields($resource, 'update', $data['fields'] ?? [], $item);

            return $resource->update($actor, $item, $fields);
        });
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function publish(?Authenticatable $actor, array $input): array
    {
        return $this->mutate($actor, $input, 'publish', 'content:publish', 'can_publish', function (ContentResource $resource, Authenticatable $actor, array $data): mixed {
            $item = $this->existingVersionedItem($resource, $actor, $data);
            $this->authorized($resource, $actor, 'publish', $item);

            return $resource->publish($actor, $item);
        });
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function archive(?Authenticatable $actor, array $input): array
    {
        return $this->mutate($actor, $input, 'archive', 'content:publish', 'can_archive', function (ContentResource $resource, Authenticatable $actor, array $data): mixed {
            $item = $this->existingVersionedItem($resource, $actor, $data);
            $this->authorized($resource, $actor, 'archive', $item);

            return $resource->archive($actor, $item);
        });
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function mutate(?Authenticatable $actor, array $input, string $operation, string $ability, string $capability, Closure $execute): array
    {
        $actor = $this->actor($actor);
        $correlationId = $this->correlationId($input['correlation_id'] ?? null);
        $rawKey = $input['idempotency_key'] ?? null;
        $contentType = is_string($input['content_type'] ?? null) ? $input['content_type'] : null;
        Log::info('content_mcp.mutation_attempted', [
            'operation' => $operation,
            'content_type' => $contentType,
            'content_id' => is_string($input['content_id'] ?? null) ? $input['content_id'] : null,
            'correlation_id' => $correlationId,
            'idempotency_key_hash' => is_string($rawKey) ? hash('sha256', $rawKey) : null,
        ]);
        $this->ability($actor, $ability);
        $resource = $this->resource($input, $capability);
        $key = $input['idempotency_key'] ?? null;
        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key)) {
            throw new ContentMcpException('validation_failed', 422, 'Provide an idempotency key between 8 and 128 safe characters.');
        }
        $requestHash = hash('sha256', json_encode($this->canonical($input), JSON_THROW_ON_ERROR));
        $identity = ['actor_type' => $actor::class, 'actor_id' => (string) $actor->getAuthIdentifier(), 'key' => $key];

        try {
            return DB::transaction(function () use ($identity, $operation, $requestHash, $resource, $actor, $input, $execute, $correlationId): array {
                $record = IdempotencyKey::query()->where($identity)->lockForUpdate()->first();
                if ($record) {
                    if ($record->operation !== $operation || ! hash_equals($record->request_hash, $requestHash)) {
                        throw new ContentMcpException('idempotency_conflict', 409);
                    }
                    if (is_array($record->response_body)) {
                        return [...$record->response_body, 'idempotent_replay' => true];
                    }
                    throw new ContentMcpException('idempotency_conflict', 409, 'The original request is still being processed.');
                }

                $record = new IdempotencyKey;
                $record->fill([
                    ...$identity, 'operation' => $operation, 'request_hash' => $requestHash,
                    'expires_at' => now()->addHours((int) config('content-mcp.limits.idempotency_ttl_hours', 168)),
                ])->save();
                $item = $execute($resource, $actor, $input);
                $result = [...$this->itemResult($resource, $item, [...$input, 'correlation_id' => $correlationId]), 'idempotent_replay' => false];
                $record->update(['response_status' => 200, 'response_body' => $result]);

                return $result;
            }, 3);
        } catch (ContentMcpException $exception) {
            Log::warning('content_mcp.mutation_failed', ['operation' => $operation, 'content_type' => $contentType, 'error' => $exception->errorCode, 'correlation_id' => $correlationId]);
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('content_mcp.mutation_failed', ['operation' => $operation, 'content_type' => $contentType, 'error' => 'unexpected_error', 'correlation_id' => $correlationId]);
            throw new ContentMcpException('unexpected_error', 500);
        }
    }

    /** @param array<string, mixed> $input */
    private function existingVersionedItem(ContentResource $resource, Authenticatable $actor, array $input): mixed
    {
        $contentId = $input['content_id'] ?? null;
        $version = $input['version'] ?? null;
        if (! is_string($contentId) || $contentId === '' || ! is_string($version) || $version === '') {
            throw new ContentMcpException('validation_failed', 422);
        }
        $item = $resource->find($actor, $contentId, null);
        if ($item === null) {
            throw new ContentMcpException('content_not_found', 404);
        }
        if (! hash_equals($resource->version($item), $version)) {
            throw new ContentMcpException('version_conflict', 409, 'Read the item again and retry with its current version.');
        }

        return $item;
    }

    /** @return array<string, mixed> */
    private function validatedFields(ContentResource $resource, string $operation, mixed $fields, mixed $item = null): array
    {
        if (! is_array($fields) || array_is_list($fields) || strlen(json_encode($fields, JSON_THROW_ON_ERROR)) > (int) config('content-mcp.limits.max_field_bytes', 100000)) {
            throw new ContentMcpException('validation_failed', 422);
        }
        $rules = $resource->rules($operation, $item);
        $allowed = array_unique(array_map(fn (string $key): string => explode('.', $key, 2)[0], array_keys($rules)));
        if (array_diff(array_keys($fields), $allowed) !== []) {
            throw new ContentMcpException('validation_failed', 422, 'Only explicitly writable fields are accepted.');
        }
        try {
            return Validator::make($fields, $rules)->validate();
        } catch (ValidationException) {
            throw new ContentMcpException('validation_failed', 422, 'Correct the content fields and retry.');
        }
    }

    /** @param array<string, mixed> $input */
    private function resource(array $input, string $capability): ContentResource
    {
        $contentType = $input['content_type'] ?? null;
        if (! is_string($contentType) || $contentType === '') {
            throw new ContentMcpException('validation_failed', 422);
        }
        $resource = $this->registry->get($contentType);
        if (($resource->capabilities()[$capability] ?? false) !== true) {
            throw ContentMcpException::unsupported();
        }

        return $resource;
    }

    private function actor(?Authenticatable $actor): Authenticatable
    {
        return $actor ?? throw new ContentMcpException('authentication_required', 401);
    }

    private function ability(Authenticatable $actor, string $ability): void
    {
        if (method_exists($actor, 'currentAccessToken') && $actor->currentAccessToken() !== null && method_exists($actor, 'tokenCan') && ! $actor->tokenCan($ability)) {
            throw new ContentMcpException('permission_denied', 403);
        }
    }

    private function authorized(ContentResource $resource, Authenticatable $actor, string $operation, mixed $item = null): void
    {
        if (! $resource->authorize($actor, $operation, $item)) {
            throw new ContentMcpException('permission_denied', 403);
        }
    }

    /** @param array<string, mixed> $input */
    private function exactlyOneSelector(array $input): void
    {
        $count = (int) (! empty($input['content_id'])) + (int) (! empty($input['slug']));
        if ($count !== 1) {
            throw new ContentMcpException('validation_failed', 422, 'Provide exactly one of content_id or slug.');
        }
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function itemResult(ContentResource $resource, mixed $item, array $input): array
    {
        $serialized = $resource->serialize($item);
        $serialized['version'] = $resource->version($item);

        return ['content_type' => $resource->contentType(), 'item' => $serialized, 'correlation_id' => $this->correlationId($input['correlation_id'] ?? null)];
    }

    private function correlationId(mixed $value): string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $value) ? $value : (string) Str::uuid();
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
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
