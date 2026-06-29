<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->uuid('api_key_id')->nullable();
            $table->string('blok_key');
            $table->string('response_type');
            $table->string('lang')->default('en');
            $table->integer('requests')->default(1);
            $table->integer('render_ms')->nullable();
            $table->string('log_date');
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'log_date'], 'idx_usage_tenant_date');
            $table->index(['blok_key', 'log_date'], 'idx_usage_blok_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_logs');
    }
};
