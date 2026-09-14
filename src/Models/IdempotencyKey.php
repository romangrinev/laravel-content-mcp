<?php

namespace GrinevStudio\LaravelContentMcp\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $table = 'content_mcp_idempotency_keys';

    protected $fillable = [
        'actor_type', 'actor_id', 'key', 'operation', 'request_hash',
        'response_status', 'response_body', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'encrypted:array',
            'expires_at' => 'datetime',
        ];
    }
}
