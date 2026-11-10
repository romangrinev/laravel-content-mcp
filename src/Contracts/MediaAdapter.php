<?php

namespace GrinevStudio\LaravelContentMcp\Contracts;

use GrinevStudio\LaravelContentMcp\Media\MediaUpload;
use Illuminate\Contracts\Auth\Authenticatable;

/** Host implementations own authorization, validation, storage and atomic receipts. */
interface MediaAdapter
{
    /** @return array<string, mixed> */
    public function capabilities(): array;

    /** @param array<string, mixed> $input */
    public function authorize(Authenticatable $actor, string $operation, array $input = []): bool;

    /**
     * Persist the operation receipt atomically with the asset.
     *
     * @return array<string, mixed>
     */
    public function upload(Authenticatable $actor, MediaUpload $upload, string $operationId): array;

    /** @return array<string, mixed> */
    public function get(Authenticatable $actor, string $mediaId): array;

    /** @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function update(Authenticatable $actor, string $mediaId, string $version, array $fields, string $operationId): array;

    /** Lock the target, validate its version, preserve omitted collections and publication state.
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function attach(Authenticatable $actor, string $contentType, string $contentId, string $version, array $fields, string $operationId): array;

    /**
     * Read only. Null is NOT proof that an operation failed.
     *
     * @return array<string, mixed>|null
     */
    public function operationResult(Authenticatable $actor, string $operationId): ?array;
}
