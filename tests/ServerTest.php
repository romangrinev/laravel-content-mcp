<?php

namespace GrinevStudio\LaravelContentMcp\Tests;

use GrinevStudio\LaravelContentMcp\ContentResourceRegistry;
use GrinevStudio\LaravelContentMcp\Server\ContentServer;
use GrinevStudio\LaravelContentMcp\Tools\ArchiveContentItemTool;
use GrinevStudio\LaravelContentMcp\Tools\GetCapabilitiesTool;

class ServerTest extends TestCase
{
    public function test_stable_tool_names_and_conditional_archive_registration(): void
    {
        ContentServer::actingAs(FakeUser::query()->create(['name' => 'Owner']))
            ->tool(GetCapabilitiesTool::class)
            ->assertOk()
            ->assertName('get_capabilities')
            ->assertStructuredContent(fn ($json) => $json->has('capabilities', 1)->etc());

        $this->assertFalse(app(ArchiveContentItemTool::class)->shouldRegister(app(ContentResourceRegistry::class)));
    }
}
