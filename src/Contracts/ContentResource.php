<?php

namespace GrinevStudio\LaravelContentMcp\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface ContentResource
{
    public function contentType(): string;

    /** @return array{can_list: bool, can_read: bool, can_create_draft: bool, can_update: bool, can_publish: bool, can_archive: bool} */
    public function capabilities(): array;

    /** @return array<string, mixed> */
    public function constraints(): array;

    /** @return array{items: array<int, array<string, mixed>>, next_cursor: ?string} */
    public function search(Authenticatable $actor, ?string $query, ?string $status, ?string $cursor, int $limit): array;

    public function find(Authenticatable $actor, ?string $contentId, ?string $slug): mixed;

    /** @return array<string, mixed> */
    public function serialize(mixed $item): array;

    public function authorize(Authenticatable $actor, string $operation, mixed $item = null): bool;

    /** @return array<string, mixed> */
    public function rules(string $operation, mixed $item = null): array;

    /** @param array<string, mixed> $fields */
    public function createDraft(Authenticatable $actor, array $fields): mixed;

    /** @param array<string, mixed> $fields */
    public function update(Authenticatable $actor, mixed $item, array $fields): mixed;

    public function publish(Authenticatable $actor, mixed $item): mixed;

    public function archive(Authenticatable $actor, mixed $item): mixed;

    public function version(mixed $item): string;
}
