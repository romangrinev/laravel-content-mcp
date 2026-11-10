<?php

namespace GrinevStudio\LaravelContentMcp\Tools;

use GrinevStudio\LaravelContentMcp\Media\MediaManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('upload_media')]
#[Description('Optional host-managed media operation. Read versions before changes; uncertain operations must be reconciled, never re-uploaded.')]
#[IsIdempotent]
class UploadMediaTool extends MediaTool
{
    public function shouldRegister(MediaManager $manager): bool
    {
        return $manager->supports('can_upload');
    }

    public function handle(Request $request): ResponseFactory
    {
        return $this->execute($request, fn (Request $request): array => $this->manager->execute($request->user(), 'upload', $request->all()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'data_base64' => $schema->string()->max(11184812)->required(),
            'filename' => $schema->string()->max(255)->required(),
            'mime_type' => $schema->string()->max(100)->required(),
            'idempotency_key' => $schema->string()->min(8)->max(128)->required(),
            'metadata' => $schema->object(),
            'correlation_id' => $schema->string()->min(8)->max(128),
        ];
    }
}
