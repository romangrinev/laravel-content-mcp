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

The stable tools are `get_capabilities`, `search_content`, `get_content_item`, `create_content_draft`, `update_content_item`, `publish_content_item`, and conditionally `archive_content_item` and `restore_content_item`. Lifecycle tools are registered only when a resource explicitly advertises the matching capability.

## Safety contract

- Every remote request is authenticated by endpoint middleware.
- Sanctum abilities are `content:read`, `content:write`, and `content:publish`.
- Every mutation requires an idempotency key; identical retries return the stored normalized result, while conflicting reuse fails.
- Update and lifecycle calls require the version returned by a read.
- Draft creation cannot publish. Update rejects lifecycle fields. Publish, archive, and restore are separate tools. Restore always returns to a resource-defined non-public state. There is no delete tool.
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

## Field validation metadata

`get_capabilities` adds `constraints.field_validation.create_draft` and `.update` for supported operations. Metadata comes only from the resource's explicit `rules()` intersected with `constraints.writable_fields`: types, required/nullable, `Rule::in`/`in`, min/max/between and safe formats. SQL rules, callbacks, sensitive fields and unrelated keys are omitted. This is a partial description; conditional and item-dependent rules remain authoritative on mutation. Rules that require an item and cannot be described without one are omitted rather than guessed.

Validation errors add a bounded `fields` map of paths to `{code, message}` violations. The envelope, tool names and HTTP/MCP behavior remain compatible. Messages never include rejected values, SQL or credentials. After a package upgrade, refresh the connection's cached capabilities in Grinev Studio.

## Optional managed media

Media is disabled by default. Configure `content-mcp.media.adapter` with a host class implementing
`Contracts\MediaAdapter`, then run the package migration before enabling the adapter.
No existing content resource must implement media methods. Capabilities include `media_capabilities: null`
until an adapter is configured and authorizes discovery. Five tools register conditionally:
`upload_media`, `get_media_upload_status`, `get_media_item`, `update_media_item`, and
`attach_media_to_content`. There are no deletion, URL import or global usage-discovery tools.

The upload input is `{data_base64, filename, mime_type, metadata?, idempotency_key}`. Only canonical,
bounded base64 JPEG/PNG/WebP is accepted, with an 8 MiB/10,000-pixel/20-megapixel ceiling intersected
with host limits. Configure a suitably bounded authenticated request body limit (12 MiB for an 8 MiB
upload). Never send local paths, credentials or raw bytes in result envelopes. The host must fully decode
images before persistence; header validation alone is insufficient. The trusted gateway strips EXIF/GPS
before upload and binds the returned checksum to those sanitized bytes. Direct authenticated admin clients
must sanitize their images beforehand, or the host may reject metadata-bearing payloads. Neither this
package nor the host adapter automatically strips metadata. Preserve the uploaded bytes and checksum
protocol rather than re-encoding already sanitized gateway uploads.

Host `upload` receives a `Media\MediaUpload` value with bytes, filename and metadata. Host `get`
returns one media object. Host `upload`/`update` return `{media: ...}` and `attach` returns `{item: ...}`
with a fresh content version. Media records used by the gateway contain string `media_id` and `version`,
HTTPS `url`, `mime_type`, integer `bytes`, `width`, `height`, SHA256 `checksum` and `metadata`.
Attachment implementations must enforce allowed content types and fields, authorization, ownership,
optimistic versions, atomic ordered replacement, omitted collections unchanged, and unchanged publication
state. Detachment keeps the binary. Metadata edits may immediately affect already-public images.

Every mutation uses a durable reservation keyed by a SHA256 of actor identity, operation and caller key.
That opaque 64-character ID is passed to the host. The host must commit an immutable operation receipt
atomically with database changes, expose it through `operationResult`, and compensate only proven storage
rollbacks. The package intentionally does not run host storage changes in an outer transaction or retry them.
Do not call the manager inside another database transaction (including transaction-wrapped test cases).
Use database migrations rather than transaction-wrapped tests for integration tests.

The package records the normalized result separately. If this final save/response is interrupted,
`get_media_upload_status` accepts the original `idempotency_key` and recovers the actor-scoped host receipt.
It returns `completed` with media, `uncertain`, or `not_found`. Neither uncertain nor not_found authorizes a
new upload. Concurrent/identical retries never re-execute an uncertain mutation; changed payloads fail with
`idempotency_conflict`. Correlation IDs do not affect replay identity. A rejected host mutation retains its
reservation: correct the request and use a new key. Never prune uncertain reservations or host receipts
without an explicit reconciliation/retention design. These records intentionally have no expiring retry lease.
