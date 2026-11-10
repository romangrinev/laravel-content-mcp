<?php

namespace GrinevStudio\LaravelContentMcp\Tests;

use GrinevStudio\LaravelContentMcp\Contracts\MediaAdapter;
use GrinevStudio\LaravelContentMcp\Media\MediaUpload;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

class FakeMediaAdapter implements MediaAdapter
{
    public int $uploads = 0;

    public bool $interrupt = false;

    public bool $receiptBeforeInterrupt = true;

    public array $receipts = [];

    public function capabilities(): array
    {
        return ['can_upload' => true, 'can_read' => true, 'can_update' => true, 'can_attach' => true,
            'allowed_mime_types' => ['image/png'], 'max_upload_bytes' => 8388608, 'metadata_fields' => ['alt']];
    }

    public function authorize(Authenticatable $actor, string $operation, array $input = []): bool
    {
        return $actor->allowed;
    }

    public function upload(Authenticatable $actor, MediaUpload $upload, string $operationId): array
    {
        $this->uploads++;
        $result = ['media' => ['media_id' => '1', 'version' => 'v1', 'checksum' => hash('sha256', $upload->bytes)]];
        if ($this->receiptBeforeInterrupt) {
            $this->receipts[$operationId] = $result;
        }
        if ($this->interrupt) {
            throw new RuntimeException('Storage outcome unknown');
        }

        return $result;
    }

    public function get(Authenticatable $actor, string $mediaId): array
    {
        return ['media_id' => $mediaId];
    }

    public function update(Authenticatable $actor, string $mediaId, string $version, array $fields, string $operationId): array
    {
        return ['media' => ['media_id' => $mediaId, 'metadata' => $fields]];
    }

    public function attach(Authenticatable $actor, string $contentType, string $contentId, string $version, array $fields, string $operationId): array
    {
        return ['item' => ['id' => $contentId, 'version' => 'v2']];
    }

    public function operationResult(Authenticatable $actor, string $operationId): ?array
    {
        return $this->receipts[$operationId] ?? null;
    }
}
