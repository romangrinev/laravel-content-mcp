<?php

namespace GrinevStudio\LaravelContentMcp\Tools;

use GrinevStudio\LaravelContentMcp\ContentResourceRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('restore_content_item')]
#[Description('Restore an archived item to the resource-defined non-public state using its current version token.')]
#[IsIdempotent]
class RestoreContentItemTool extends ContentTool
{
    public function shouldRegister(ContentResourceRegistry $registry): bool
    {
        return $registry->supports('can_restore');
    }

    public function handle(Request $request): ResponseFactory
    {
        return $this->execute($request, fn (Request $request): array => $this->manager->restore($request->user(), $request->all()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content_type' => $schema->string()->max(64)->required(),
            'content_id' => $schema->string()->max(255)->required(),
            'version' => $schema->string()->max(255)->required(),
            'idempotency_key' => $schema->string()->min(8)->max(128)->required(),
            'correlation_id' => $schema->string()->min(8)->max(128),
        ];
    }
}
