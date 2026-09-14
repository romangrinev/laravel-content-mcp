<?php

namespace GrinevStudio\LaravelContentMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_content')]
#[Description('Search one explicitly registered content type using bounded pagination.')]
#[IsReadOnly]
class SearchContentTool extends ContentTool
{
    public function handle(Request $request): ResponseFactory
    {
        return $this->execute($request, fn (Request $request): array => $this->manager->search($request->user(), $request->all()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content_type' => $schema->string()->max(64)->required(),
            'query' => $schema->string()->max(500),
            'status' => $schema->string()->max(64),
            'cursor' => $schema->string()->max(500),
            'limit' => $schema->integer()->min(1)->max((int) config('content-mcp.limits.max_page_size', 50)),
            'correlation_id' => $schema->string()->min(8)->max(128),
        ];
    }
}
