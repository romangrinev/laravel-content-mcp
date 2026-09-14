<?php

namespace GrinevStudio\LaravelContentMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_content_item')]
#[Description('Get one content item by exactly one stable ID or slug and return its version token.')]
#[IsReadOnly]
class GetContentItemTool extends ContentTool
{
    public function handle(Request $request): ResponseFactory
    {
        return $this->execute($request, fn (Request $request): array => $this->manager->get($request->user(), $request->all()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content_type' => $schema->string()->max(64)->required(),
            'content_id' => $schema->string()->max(255),
            'slug' => $schema->string()->max(255),
            'correlation_id' => $schema->string()->min(8)->max(128),
        ];
    }
}
