<?php

return [
    'name' => env('CONTENT_MCP_NAME', config('app.name').' Content'),
    'version' => '0.1.1',
    'instructions' => 'Manage only explicitly registered application content. Read before write and use separate publish or archive tools.',

    'resources' => [],

    'web' => [
        'enabled' => true,
        'path' => '/mcp/content',
        'middleware' => ['api', 'auth:sanctum', 'throttle:content-mcp'],
    ],

    'local' => [
        'enabled' => true,
        'name' => 'content',
    ],

    'limits' => [
        'max_page_size' => 50,
        'max_field_bytes' => 100_000,
        'idempotency_ttl_hours' => 168,
        'requests_per_minute' => 60,
    ],
];
