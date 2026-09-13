# Laravel Content MCP

A planned open-source, resource-driven MCP content server for Laravel applications.

> **Status:** planning only. No installable package has been released yet. The first implementation target will be Value Max Construction, followed by extraction and validation against additional Laravel applications.

## Why this project exists

Laravel applications often store content in application-specific Eloquent models and manage it through custom Filament resources. Exposing those models directly to an AI client would be unsafe, while creating a bespoke MCP server for every application would duplicate transport, authentication, validation, and concurrency code.

Laravel Content MCP will provide a small reusable layer for exposing an explicitly approved subset of a Laravel application's content operations through stable MCP tools. It will be convenient to connect to Grinev Studio MCP, but it will not require that hosted service. Developers will be able to connect compatible MCP clients directly or use their own gateway.

## Design goals

- Build on the official `laravel/mcp` package instead of implementing the MCP protocol.
- Keep each Laravel application authoritative for its models, validation, authorization, and lifecycle rules.
- Expose only resources and fields explicitly registered by the application.
- Keep MCP tool names and normalized response contracts stable across different sites.
- Support direct/self-hosted use without a dependency on Grinev Studio infrastructure.
- Provide a straightforward server-to-server setup for the Grinev Studio hosted control plane.
- Remain independent of Filament while allowing applications to reuse the same domain services and validation rules.

## Proposed architecture

```text
Any compatible MCP client                 Grinev Studio MCP
             |                                    |
             +---------------+--------------------+
                             |
                    Laravel Content MCP
                             |
              Explicit resource definitions
                             |
           Eloquent models and domain services
```

The package will provide the MCP transport integration, common tools, resource registry, normalized results, concurrency checks, idempotency support, and safe error handling. The host application will provide one resource definition for each content type it wants to expose.

## Stable MCP surface

The initial server is expected to expose a small set of generic tools:

```text
get_capabilities
search_content
get_content_item
create_content_draft
update_content_item
publish_content_item
archive_content_item (only when explicitly supported)
```

The set of tools stays stable as applications add content types. A `content_type` argument selects a registered application resource such as `service`, `area`, `service_area_page`, `project`, or `review`.

## Defining application resources

Applications will explicitly register their allowed resources in configuration:

```php
return [
    'resources' => [
        'service' => App\Mcp\Content\ServiceContentResource::class,
        'area' => App\Mcp\Content\AreaContentResource::class,
        'service_area_page' => App\Mcp\Content\ServiceAreaPageContentResource::class,
    ],
];
```

Each resource definition will be responsible for:

- the Eloquent model or domain service it uses;
- searchable and readable fields;
- writable fields and Laravel validation rules;
- authorization policy checks;
- normalized serialization;
- public and preview URLs;
- the optimistic-concurrency version token;
- supported lifecycle operations such as draft, publish, and archive.

The package will not automatically expose Eloquent models, database columns, or Filament resources. Filament form definitions are administrative UI configuration and must not become an implicit public API.

## Safety requirements

Every implementation and release must preserve these invariants:

- Authentication is required for every remote request.
- Tokens are hashed at rest and credentials are never returned in results or logs.
- Every mutation has an explicit resource type, content identifier where applicable, and idempotency key.
- Updates require a current version token when optimistic concurrency is supported.
- Ordinary updates cannot publish or archive content.
- Publishing and archival are separate, consequential tools.
- Physical deletion is not part of the initial package.
- The host application performs authorization and validation server-side.
- Every mutation attempt can be correlated and audited without storing secrets.
- Request size, pagination, timeouts, rate limits, and outbound behavior are bounded.

## Authentication modes

The planned first release will document two deployment modes:

1. **Direct/self-hosted MCP:** protect the MCP endpoint with the authentication supported by `laravel/mcp`, using Laravel Passport/OAuth when required by the client or Sanctum for token-based clients.
2. **Grinev Studio connection:** use a dedicated least-privilege site integration token. The hosted control plane stores that credential encrypted and proxies only authorized normalized operations.

Human Filament passwords must never be used as integration credentials.

## Initial Value Max Construction mapping

The first real application will validate the abstraction against these content types:

```text
service_area_page -> ServiceAreaContent
service           -> Service
area              -> Area
project           -> Project
review            -> Review
```

Text-based read flows will be completed before media support. `ServiceAreaContent`, which already has a publication state, will be the first candidate for draft, update, and explicit publish operations. Resources without a safe lifecycle will advertise only the operations they actually support.

Inquiries, administrator accounts, arbitrary database access, media uploads, billing, and autonomous content changes are outside the first release.

## Planned v0.1.0 scope

1. Establish package structure, automated tests, static analysis, formatting, and CI.
2. Integrate the official `laravel/mcp` server package.
3. Implement the resource registry and capability discovery.
4. Implement normalized search and read tools.
5. Implement idempotent draft, update, and explicit publish tools.
6. Add Sanctum-based integration authentication and document optional OAuth setup.
7. Add a complete example resource and installation guide.
8. Validate the package in Value Max Construction.
9. Test with MCP Inspector, a direct MCP client, and Grinev Studio MCP.
10. Publish `v0.1.0` on GitHub and Packagist after the public contracts are documented.

## Compatibility plan

The initial target is PHP 8.3 and currently supported Laravel versions compatible with the official `laravel/mcp` package. Exact Composer constraints will be selected when implementation begins and will be tested in a version matrix.

## Planned distribution

- Composer package: `grinev-studio/laravel-content-mcp`
- PHP namespace: `GrinevStudio\LaravelContentMcp`
- License: MIT
- Source and issue tracker: this public repository
- Releases: semantic versioning, beginning with `v0.1.0`

## Current next step

Upgrade the first integration application to a supported Laravel and Filament stack. Then implement one complete vertical slice using `ServiceAreaContent` before generalizing additional resource behavior.

