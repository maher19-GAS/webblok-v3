<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('key_hash')->unique();
            $table->string('key_prefix');
            $table->string('environment')->default('live');
            $table->text('scopes')->default('["bloks:read"]');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id', 'idx_api_keys_tenant');
            $table->index('key_prefix', 'idx_api_keys_prefix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
