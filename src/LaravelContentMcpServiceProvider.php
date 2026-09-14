<?php

namespace GrinevStudio\LaravelContentMcp;

use GrinevStudio\LaravelContentMcp\Server\ContentServer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

class LaravelContentMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/content-mcp.php', 'content-mcp');

        $this->app->singleton(ContentResourceRegistry::class, fn (): ContentResourceRegistry => new ContentResourceRegistry(
            config('content-mcp.resources', []),
        ));
        $this->app->singleton(ContentManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/content-mcp.php' => config_path('content-mcp.php'),
        ], 'content-mcp-config');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        RateLimiter::for('content-mcp', fn (Request $request): Limit => Limit::perMinute(
            (int) config('content-mcp.limits.requests_per_minute', 60),
        )->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        if ((bool) config('content-mcp.web.enabled', true)) {
            Mcp::web((string) config('content-mcp.web.path', '/mcp/content'), ContentServer::class)
                ->middleware(config('content-mcp.web.middleware', []));
        }

        if ((bool) config('content-mcp.local.enabled', true)) {
            Mcp::local((string) config('content-mcp.local.name', 'content'), ContentServer::class);
        }
    }
}
