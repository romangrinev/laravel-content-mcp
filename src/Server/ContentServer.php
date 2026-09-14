<?php

namespace GrinevStudio\LaravelContentMcp\Server;

use GrinevStudio\LaravelContentMcp\Tools\ArchiveContentItemTool;
use GrinevStudio\LaravelContentMcp\Tools\CreateContentDraftTool;
use GrinevStudio\LaravelContentMcp\Tools\GetCapabilitiesTool;
use GrinevStudio\LaravelContentMcp\Tools\GetContentItemTool;
use GrinevStudio\LaravelContentMcp\Tools\PublishContentItemTool;
use GrinevStudio\LaravelContentMcp\Tools\SearchContentTool;
use GrinevStudio\LaravelContentMcp\Tools\UpdateContentItemTool;
use Laravel\Mcp\Server;

class ContentServer extends Server
{
    protected string $name = 'Laravel Content MCP';

    protected string $version = '0.1.1';

    protected string $instructions = 'Manage only explicitly registered content. Read before writing. Creation is draft-only; publishing and archival are separate consequential actions.';

    protected array $tools = [
        GetCapabilitiesTool::class,
        SearchContentTool::class,
        GetContentItemTool::class,
        CreateContentDraftTool::class,
        UpdateContentItemTool::class,
        PublishContentItemTool::class,
        ArchiveContentItemTool::class,
    ];
}
