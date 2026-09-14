<?php

namespace GrinevStudio\LaravelContentMcp\Exceptions;

use RuntimeException;

class ContentMcpException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 400,
        public readonly ?string $recoveryHint = null,
    ) {
        parent::__construct($errorCode);
    }

    public static function unsupported(): self
    {
        return new self('capability_not_supported', 409);
    }
}
