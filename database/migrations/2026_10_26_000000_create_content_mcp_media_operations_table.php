<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_mcp_media_operations', function (Blueprint $table): void {
            $table->string('operation_id', 64)->primary();
            $table->string('operation', 16);
            $table->string('request_hash', 64);
            $table->json('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_mcp_media_operations');
    }
};
