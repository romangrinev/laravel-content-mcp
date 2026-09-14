# Laravel Content MCP

`romangrinev/laravel-content-mcp` is a reusable, resource-driven MCP content server for Laravel applications. It uses the official `laravel/mcp` transport and works directly with compatible MCP clients; Grinev Studio is optional.

The package exposes only resources and fields explicitly registered by the host application. It never discovers Eloquent models, database columns, `$fillable`, or Filament resources.

## Requirements

- PHP 8.3+
- Laravel 12.41.1+ or 13.x, matching `laravel/mcp` 0.9. Laravel 11 is intentionally excluded because its currently resolvable releases are blocked by Composer security advisories.
- Sanctum for bearer-token remote access, or host-configured Passport/OAuth middleware

## Installation

Install the tagged release through Composer; no local checkout or custom repository entry is required:

```bash
composer require romangrinev/laravel-content-mcp:^0.1.1
php artisan vendor:publish --tag=content-mcp-config
php artisan migrate
```

Package discovery registers the service provider, Streamable HTTP endpoint, local server, rate limiter, and idempotency migration.

## Registering resources

Register an explicit content-type-to-class map in `config/content-mcp.php`:

```php
return [
    'resources' => [
        'service' => App\Mcp\Content\ServiceContentResource::class,
        'area' => App\Mcp\Content\AreaContentResource::class,
        'service_area_page' => App\Mcp\Content\ServiceAreaPageContentResource::class,
    ],
];
```

Each class extends `GrinevStudio\LaravelContentMcp\Resources\ContentResource` and must explicitly implement its content type, capability flags, constraints, search and lookup behavior, field allowlists, validation, authorization, serialization, URLs, version token, and supported lifecycle operations.

The stable tools are `get_capabilities`, `search_content`, `get_content_item`, `create_content_draft`, `update_content_item`, `publish_content_item`, and conditionally `archive_content_item`. Archive is not registered unless a resource explicitly advertises `can_archive`.

## Safety contract

- Every remote request is authenticated by endpoint middleware.
- Sanctum abilities are `content:read`, `content:write`, and `content:publish`.
- Every mutation requires an idempotency key; identical retries return the stored normalized result, while conflicting reuse fails.
- Update and lifecycle calls require the version returned by a read.
- Draft creation cannot publish. Update rejects lifecycle fields. Publish and archive are separate consequential tools. There is no delete tool.
- The host application remains authoritative for authorization and validation.
- Pagination, field payloads, request rates, and idempotency retention are bounded in configuration.
- Logs contain correlation IDs and hashes rather than bearer tokens or raw idempotency keys. Errors contain stable codes without stack traces or credentials.

## Direct/self-hosted MCP

The default endpoint is `/mcp/content`, protected by `api`, `auth:sanctum`, and `throttle:content-mcp`. Give the client a dedicated token with minimum abilities:

```text
Authorization: Bearer <dedicated-token>
```

For clients requiring OAuth 2.1, configure Laravel Passport and replace `content-mcp.web.middleware` with the application's OAuth middleware as documented by `laravel/mcp`. Never remove authentication from a remotely reachable endpoint.

A trusted local client can use `php artisan mcp:start content`. Inspect with `php artisan mcp:inspector content` or `php artisan mcp:inspector mcp/content`, adding Authorization for the web server.

## Grinev Studio connection

Create a separate least-privilege Sanctum token in the host application. Enter the site base HTTPS URL and token in the Grinev Studio Laravel site connection. The control plane stores the token encrypted, connects to `<base-url>/mcp/content`, discovers capabilities, and invokes only generic tools. Its public Node runtime never receives the site token.

## Development

```bash
composer install
composer check
```

CI runs formatting, PHPStan, and PHPUnit against all supported Laravel/Testbench lines.
