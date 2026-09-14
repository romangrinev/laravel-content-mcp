<?php

namespace GrinevStudio\LaravelContentMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_content_item')]
#[Description('Update allowed non-lifecycle fields using a current version token. This tool cannot publish.')]
#[IsIdempotent]
class UpdateContentItemTool extends ContentTool
{
    public function handle(Request $request): ResponseFactory
    {
        return $this->execute($request, fn (Request $request): array => $this->manager->update($request->user(), $request->all()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content_type' => $schema->string()->max(64)->required(),
            'content_id' => $schema->string()->max(255)->required(),
            'version' => $schema->string()->max(255)->required(),
            'fields' => $schema->object()->required(),
            'idempotency_key' => $schema->string()->min(8)->max(128)->required(),
            'correlation_id' => $schema->string()->min(8)->max(128),
        ];
    }
}
