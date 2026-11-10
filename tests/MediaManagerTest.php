<?php

namespace GrinevStudio\LaravelContentMcp\Tests;

use GrinevStudio\LaravelContentMcp\ContentManager;
use GrinevStudio\LaravelContentMcp\Exceptions\ContentMcpException;
use GrinevStudio\LaravelContentMcp\Media\MediaManager;
use GrinevStudio\LaravelContentMcp\Server\ContentServer;
use GrinevStudio\LaravelContentMcp\Tools\UploadMediaTool;
use Illuminate\Support\Facades\DB;

class MediaManagerTest extends TestCase
{
    private function enable(): FakeMediaAdapter
    {
        config(['content-mcp.media.adapter' => FakeMediaAdapter::class]);
        $adapter = new FakeMediaAdapter;
        app()->instance(FakeMediaAdapter::class, $adapter);

        return $adapter;
    }

    private function input(): array
    {
        return ['data_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jp1sAAAAASUVORK5CYII=',
            'filename' => 'pixel.png', 'mime_type' => 'image/png', 'idempotency_key' => 'upload-test-123'];
    }

    private function actor(): FakeUser
    {
        return FakeUser::query()->create(['name' => 'Owner']);
    }

    public function test_media_is_disabled_by_default(): void
    {
        $manager = app(MediaManager::class);
        $this->assertFalse(app(UploadMediaTool::class)->shouldRegister($manager));
        $this->assertNull(app(ContentManager::class)->capabilities($this->actor())['media_capabilities']);
    }

    public function test_identical_retry_ignores_correlation_and_does_not_upload_twice(): void
    {
        $adapter = $this->enable();
        $manager = app(MediaManager::class);
        $actor = $this->actor();
        $first = $manager->execute($actor, 'upload', $this->input());
        $retry = $manager->execute($actor, 'upload', [...$this->input(), 'correlation_id' => 'new-correlation']);
        $this->assertSame('completed', $first['status']);
        $this->assertTrue($retry['idempotent_replay']);
        $this->assertSame(1, $adapter->uploads);
        $this->assertSame($first['media'], $retry['media']);
    }

    public function test_conflicting_retry_is_rejected(): void
    {
        $this->enable();
        $actor = $this->actor();
        app(MediaManager::class)->execute($actor, 'upload', $this->input());
        $this->expectException(ContentMcpException::class);
        $this->expectExceptionMessage('idempotency_conflict');
        app(MediaManager::class)->execute($actor, 'upload', [...$this->input(), 'filename' => 'different.png']);
    }

    public function test_status_recovers_host_receipt_after_interrupted_response(): void
    {
        $adapter = $this->enable();
        $adapter->interrupt = true;
        $actor = $this->actor();
        try {
            app(MediaManager::class)->execute($actor, 'upload', $this->input());
            $this->fail('Expected interrupted upload');
        } catch (ContentMcpException $e) {
            $this->assertSame('upload_reconciliation_required', $e->errorCode);
        }
        $this->assertNull(DB::table('content_mcp_media_operations')->value('result'));
        $result = app(MediaManager::class)->execute($actor, 'status', ['idempotency_key' => 'upload-test-123']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $adapter->uploads);
        $this->assertNotNull(DB::table('content_mcp_media_operations')->value('result'));
        $this->assertSame('not_found', app(MediaManager::class)->execute($this->actor(), 'status', ['idempotency_key' => 'upload-test-123'])['status']);
    }

    public function test_unknown_outcome_never_reexecutes(): void
    {
        $adapter = $this->enable();
        $adapter->interrupt = true;
        $adapter->receiptBeforeInterrupt = false;
        $actor = $this->actor();
        for ($i = 0; $i < 2; $i++) {
            try {
                app(MediaManager::class)->execute($actor, 'upload', $this->input());
                $this->fail('Expected uncertain result');
            } catch (ContentMcpException $e) {
                $this->assertSame('upload_reconciliation_required', $e->errorCode);
            }
        }
        $this->assertSame('uncertain', app(MediaManager::class)->execute($actor, 'status', ['idempotency_key' => 'upload-test-123'])['status']);
        $this->assertSame(1, $adapter->uploads);
    }

    public function test_invalid_base64_has_no_side_effects(): void
    {
        $adapter = $this->enable();
        try {
            app(MediaManager::class)->execute($this->actor(), 'upload', [...$this->input(), 'data_base64' => 'not base64']);
            $this->fail('Expected validation error');
        } catch (ContentMcpException $e) {
            $this->assertSame('validation_failed', $e->errorCode);
        }
        $this->assertSame(0, $adapter->uploads);
        $this->assertSame(0, DB::table('content_mcp_media_operations')->count());
    }

    public function test_enabled_tool_returns_gateway_receipt_shape(): void
    {
        $this->enable();
        ContentServer::actingAs($this->actor())
            ->tool(UploadMediaTool::class, $this->input())
            ->assertOk()
            ->assertName('upload_media')
            ->assertStructuredContent(fn ($json) => $json->where('status', 'completed')->has('media.media_id')->etc());
    }

    public function test_outer_transaction_cannot_erase_upload_reservation(): void
    {
        $adapter = $this->enable();
        $actor = $this->actor();
        DB::beginTransaction();
        try {
            app(MediaManager::class)->upload($actor, $this->input());
            $this->fail('Expected unsafe transaction to be rejected');
        } catch (ContentMcpException $e) {
            $this->assertSame('upload_reconciliation_required', $e->errorCode);
        } finally {
            DB::rollBack();
        }
        $this->assertSame(0, $adapter->uploads);
    }

    public function test_authorization_precedes_receipt_reads(): void
    {
        $this->enable();
        $actor = $this->actor();
        app(MediaManager::class)->execute($actor, 'upload', $this->input());
        $actor->allowed = false;
        $this->expectException(ContentMcpException::class);
        $this->expectExceptionMessage('permission_denied');
        app(MediaManager::class)->execute($actor, 'status', ['idempotency_key' => 'upload-test-123']);
    }
}
