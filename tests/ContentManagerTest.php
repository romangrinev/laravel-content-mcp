<?php

namespace GrinevStudio\LaravelContentMcp\Tests;

use GrinevStudio\LaravelContentMcp\ContentManager;
use GrinevStudio\LaravelContentMcp\Exceptions\ContentMcpException;
use Illuminate\Support\Facades\Log;

class ContentManagerTest extends TestCase
{
    private ContentManager $manager;

    private FakeUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(ContentManager::class);
        $this->user = FakeUser::query()->create(['name' => 'Owner']);
    }

    public function test_lists_capabilities_and_reads_and_searches_items(): void
    {
        $this->assertSame('article', $this->manager->capabilities($this->user)['capabilities'][0]['content_type']);
        $this->assertSame('First', $this->manager->search($this->user, ['content_type' => 'article'])['items'][0]['title']);
        $this->assertSame('v1', $this->manager->get($this->user, ['content_type' => 'article', 'content_id' => '1'])['item']['version']);
    }

    public function test_requires_authentication_and_resource_authorization(): void
    {
        $this->expectCode('authentication_required', fn () => $this->manager->capabilities(null));
        $this->user->allowed = false;
        $this->expectCode('permission_denied', fn () => $this->manager->get($this->user, ['content_type' => 'article', 'content_id' => '1']));
    }

    public function test_rejects_validation_and_unsupported_capability(): void
    {
        $this->expectCode('validation_failed', fn () => $this->manager->createDraft($this->user, ['content_type' => 'article', 'fields' => [], 'idempotency_key' => 'draft-key']));
        $this->expectCode('capability_not_supported', fn () => $this->manager->archive($this->user, ['content_type' => 'article', 'content_id' => '1', 'version' => 'v1', 'idempotency_key' => 'archive-key']));
    }

    public function test_update_requires_current_version_and_cannot_publish(): void
    {
        $this->expectCode('version_conflict', fn () => $this->manager->update($this->user, ['content_type' => 'article', 'content_id' => '1', 'version' => 'stale', 'fields' => ['title' => 'Changed'], 'idempotency_key' => 'update-stale']));
        $this->expectCode('validation_failed', fn () => $this->manager->update($this->user, ['content_type' => 'article', 'content_id' => '1', 'version' => 'v1', 'fields' => ['title' => 'Changed', 'published' => true], 'idempotency_key' => 'update-publish']));
        $this->assertFalse(FakeContentResource::$items['1']->published);
    }

    public function test_validation_recovery_hint_names_the_rejected_field(): void
    {
        try {
            $this->manager->update($this->user, [
                'content_type' => 'article', 'content_id' => '1', 'version' => 'v1',
                'fields' => ['title' => str_repeat('x', 101)], 'idempotency_key' => 'invalid-title-length',
            ]);
            $this->fail('Expected validation to fail.');
        } catch (ContentMcpException $exception) {
            $this->assertSame('validation_failed', $exception->errorCode);
            $this->assertStringContainsString('title:', $exception->recoveryHint);
            $this->assertStringContainsString('100 characters', $exception->recoveryHint);
        }

        try {
            $this->manager->update($this->user, [
                'content_type' => 'article', 'content_id' => '1', 'version' => 'v1',
                'fields' => ['published' => true], 'idempotency_key' => 'invalid-publish-field',
            ]);
            $this->fail('Expected an unwritable field to fail.');
        } catch (ContentMcpException $exception) {
            $this->assertSame('validation_failed', $exception->errorCode);
            $this->assertStringContainsString('published', $exception->recoveryHint);
        }
    }

    public function test_idempotent_replay_does_not_repeat_mutation_and_conflicting_reuse_fails(): void
    {
        $input = ['content_type' => 'article', 'fields' => ['title' => 'Draft'], 'idempotency_key' => 'same-draft-key', 'correlation_id' => 'correlation-1'];
        $first = $this->manager->createDraft($this->user, $input);
        $second = $this->manager->createDraft($this->user, $input);

        $this->assertFalse($first['idempotent_replay']);
        $this->assertTrue($second['idempotent_replay']);
        $this->assertSame(1, FakeContentResource::$mutations);
        $this->expectCode('idempotency_conflict', fn () => $this->manager->createDraft($this->user, [...$input, 'fields' => ['title' => 'Other']]));
    }

    public function test_publish_is_explicit_and_logs_only_redacted_context(): void
    {
        Log::spy();
        $result = $this->manager->publish($this->user, ['content_type' => 'article', 'content_id' => '1', 'version' => 'v1', 'idempotency_key' => 'publish-secret-key', 'correlation_id' => 'correlation-2']);

        $this->assertSame('published', $result['item']['status']);
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context);

            return $message === 'content_mcp.mutation_attempted' && ! str_contains($encoded, 'publish-secret-key') && ! array_key_exists('authorization', $context);
        })->once();
    }

    private function expectCode(string $code, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected {$code}.");
        } catch (ContentMcpException $exception) {
            $this->assertSame($code, $exception->errorCode);
        }
    }
}
