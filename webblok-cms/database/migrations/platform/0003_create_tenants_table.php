<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans');
            $table->foreignUuid('owner_id')->constrained('users');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('subdomain')->unique();
            $table->string('database_path');
            $table->string('storage_path');
            $table->string('status')->default('provisioning');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('billing_email');
            $table->text('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug', 'idx_tenants_slug');
            $table->index('domain', 'idx_tenants_domain');
            $table->index('subdomain', 'idx_tenants_subdomain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
