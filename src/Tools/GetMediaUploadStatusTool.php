<?php

namespace GrinevStudio\LaravelContentMcp\Tools;

use GrinevStudio\LaravelContentMcp\Media\MediaManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_media_upload_status')]
#[Description('Optional host-managed media operation. Read versions before changes; uncertain operations must be reconciled, never re-uploaded.')]
#[IsReadOnly]
class GetMediaUploadStatusTool extends MediaTool
{
    public function shouldRegister(MediaManager $manager): bool
    {
        return $manager->supports('can_upload');
    }

    public function handle(Request $request): ResponseFactory
    {
        return $this->execute($request, fn (Request $request): array => $this->manager->execute($request->user(), 'status', $request->all()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'idempotency_key' => $schema->string()->min(8)->max(128)->required(),
            'correlation_id' => $schema->string()->min(8)->max(128),
        ];
    }
}
