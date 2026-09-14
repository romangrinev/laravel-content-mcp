<?php

namespace GrinevStudio\LaravelContentMcp\Resources;

use GrinevStudio\LaravelContentMcp\Contracts\ContentResource as ContentResourceContract;
use GrinevStudio\LaravelContentMcp\Exceptions\ContentMcpException;
use Illuminate\Contracts\Auth\Authenticatable;

abstract class ContentResource implements ContentResourceContract
{
    public function constraints(): array
    {
        return [];
    }

    /** @param array<string, mixed> $fields */
    public function createDraft(Authenticatable $actor, array $fields): mixed
    {
        throw ContentMcpException::unsupported();
    }

    /** @param array<string, mixed> $fields */
    public function update(Authenticatable $actor, mixed $item, array $fields): mixed
    {
        throw ContentMcpException::unsupported();
    }

    public function publish(Authenticatable $actor, mixed $item): mixed
    {
        throw ContentMcpException::unsupported();
    }

    public function archive(Authenticatable $actor, mixed $item): mixed
    {
        throw ContentMcpException::unsupported();
    }
}
