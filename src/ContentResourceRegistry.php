<?php

namespace GrinevStudio\LaravelContentMcp;

use GrinevStudio\LaravelContentMcp\Contracts\ContentResource;
use InvalidArgumentException;

class ContentResourceRegistry
{
    /** @var array<string, ContentResource> */
    private array $resources = [];

    /** @param array<string, class-string<ContentResource>> $resources */
    public function __construct(array $resources = [])
    {
        foreach ($resources as $contentType => $resource) {
            $instance = app($resource);

            if ($instance->contentType() !== $contentType) {
                throw new InvalidArgumentException("Invalid content MCP resource registration for [{$contentType}].");
            }

            $this->resources[$contentType] = $instance;
        }
    }

    /** @return array<string, ContentResource> */
    public function all(): array
    {
        return $this->resources;
    }

    public function get(string $contentType): ContentResource
    {
        return $this->resources[$contentType] ?? throw new Exceptions\ContentMcpException('capability_not_supported', 409);
    }

    public function supports(string $capability): bool
    {
        foreach ($this->resources as $resource) {
            if (($resource->capabilities()[$capability] ?? false) === true) {
                return true;
            }
        }

        return false;
    }
}
