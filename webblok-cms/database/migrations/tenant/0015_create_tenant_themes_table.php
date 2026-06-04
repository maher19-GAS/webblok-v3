<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('tenant_themes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('item_slug');
            $table->boolean('is_active')->default(false);
            $table->text('custom_vars')->nullable();
            $table->timestamp('installed_at');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('tenant_themes');
    }
};
