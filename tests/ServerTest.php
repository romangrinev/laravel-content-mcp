<?php

namespace GrinevStudio\LaravelContentMcp\Tests;

use GrinevStudio\LaravelContentMcp\ContentResourceRegistry;
use GrinevStudio\LaravelContentMcp\Server\ContentServer;
use GrinevStudio\LaravelContentMcp\Tools\ArchiveContentItemTool;
use GrinevStudio\LaravelContentMcp\Tools\GetCapabilitiesTool;
use GrinevStudio\LaravelContentMcp\Tools\UpdateContentItemTool;

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

    public function test_field_errors_are_returned_in_structured_mcp_results(): void
    {
        ContentServer::actingAs(FakeUser::query()->create(['name' => 'Owner']))
            ->tool(UpdateContentItemTool::class, [
                'content_type' => 'article', 'content_id' => '1', 'version' => 'v1',
                'fields' => ['title' => 'Valid title', 'best_season' => 'anything'], 'idempotency_key' => 'server-invalid-enum',
            ])
            ->assertHasErrors()
            ->assertStructuredContent(fn ($json) => $json->where('error', 'validation_failed')->where('fields.best_season.0.code', 'enum')->etc());
    }
}
