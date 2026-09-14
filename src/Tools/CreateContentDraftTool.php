<?php

namespace GrinevStudio\LaravelContentMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('create_content_draft')]
#[Description('Create an unpublished draft for a content type that explicitly supports drafts.')]
#[IsIdempotent]
class CreateContentDraftTool extends ContentTool
{
    public function handle(Request $request): ResponseFactory
    {
        return $this->execute($request, fn (Request $request): array => $this->manager->createDraft($request->user(), $request->all()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content_type' => $schema->string()->max(64)->required(),
            'fields' => $schema->object()->required(),
            'idempotency_key' => $schema->string()->min(8)->max(128)->required(),
            'correlation_id' => $schema->string()->min(8)->max(128),
        ];
    }
}
