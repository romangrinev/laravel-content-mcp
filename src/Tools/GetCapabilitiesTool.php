<?php

namespace GrinevStudio\LaravelContentMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_capabilities')]
#[Description('List explicitly registered content types, safe operations, fields, and lifecycle constraints.')]
#[IsReadOnly]
class GetCapabilitiesTool extends ContentTool
{
    public function handle(Request $request): ResponseFactory
    {
        return $this->execute($request, fn (Request $request): array => $this->manager->capabilities($request->user(), $request->get('correlation_id')));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['correlation_id' => $schema->string()->min(8)->max(128)];
    }
}
