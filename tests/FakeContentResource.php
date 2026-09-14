<?php

namespace GrinevStudio\LaravelContentMcp\Tests;

use GrinevStudio\LaravelContentMcp\Resources\ContentResource;
use Illuminate\Contracts\Auth\Authenticatable;
use stdClass;

class FakeContentResource extends ContentResource
{
    /** @var array<string, stdClass> */
    public static array $items = [];

    public static int $mutations = 0;

    public static function reset(): void
    {
        self::$items = [
            '1' => (object) ['id' => '1', 'slug' => 'first', 'title' => 'First', 'published' => false, 'version' => 'v1'],
        ];
        self::$mutations = 0;
    }

    public function contentType(): string
    {
        return 'article';
    }

    public function capabilities(): array
    {
        return ['can_list' => true, 'can_read' => true, 'can_create_draft' => true, 'can_update' => true, 'can_publish' => true, 'can_archive' => false];
    }

    public function constraints(): array
    {
        return ['readable_fields' => ['title', 'published'], 'writable_fields' => ['title']];
    }

    public function search(Authenticatable $actor, ?string $query, ?string $status, ?string $cursor, int $limit): array
    {
        $items = array_values(array_filter(self::$items, fn (stdClass $item): bool => $query === null || str_contains($item->title, $query)));

        return ['items' => array_map(fn (stdClass $item): array => $this->serialize($item), array_slice($items, 0, $limit)), 'next_cursor' => null];
    }

    public function find(Authenticatable $actor, ?string $contentId, ?string $slug): mixed
    {
        if ($contentId !== null) {
            return self::$items[$contentId] ?? null;
        }

        foreach (self::$items as $item) {
            if ($item->slug === $slug) {
                return $item;
            }
        }

        return null;
    }

    public function serialize(mixed $item): array
    {
        return ['id' => $item->id, 'slug' => $item->slug, 'title' => $item->title, 'status' => $item->published ? 'published' : 'draft'];
    }

    public function authorize(Authenticatable $actor, string $operation, mixed $item = null): bool
    {
        return $actor instanceof FakeUser && $actor->allowed;
    }

    public function rules(string $operation, mixed $item = null): array
    {
        return ['title' => ['required', 'string', 'max:100']];
    }

    public function createDraft(Authenticatable $actor, array $fields): mixed
    {
        self::$mutations++;
        $id = (string) (count(self::$items) + 1);

        return self::$items[$id] = (object) ['id' => $id, 'slug' => 'draft-'.$id, 'title' => $fields['title'], 'published' => false, 'version' => 'v1'];
    }

    public function update(Authenticatable $actor, mixed $item, array $fields): mixed
    {
        self::$mutations++;
        $item->title = $fields['title'];
        $item->version = 'v2';

        return $item;
    }

    public function publish(Authenticatable $actor, mixed $item): mixed
    {
        self::$mutations++;
        $item->published = true;
        $item->version = 'v2';

        return $item;
    }

    public function version(mixed $item): string
    {
        return $item->version;
    }
}
