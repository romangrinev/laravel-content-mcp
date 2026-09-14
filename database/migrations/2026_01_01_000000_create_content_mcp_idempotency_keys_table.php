<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_mcp_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type');
            $table->string('actor_id');
            $table->string('key', 128);
            $table->string('operation', 64);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->mediumText('response_body')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['actor_type', 'actor_id', 'key'], 'content_mcp_actor_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_mcp_idempotency_keys');
    }
};
