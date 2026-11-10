<?php

namespace GrinevStudio\LaravelContentMcp\Media;

final readonly class MediaUpload
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $bytes, public string $filename, public array $metadata = []) {}
}
