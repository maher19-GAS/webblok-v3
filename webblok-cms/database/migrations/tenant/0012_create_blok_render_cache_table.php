<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('blok_render_cache', function (Blueprint $table): void {
            $table->string('cache_key')->primary();
            $table->text('html');
            $table->string('data_hash');
            $table->string('lang');
            $table->string('blok_key');
            $table->timestamp('rendered_at')->useCurrent();
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('blok_render_cache');
    }
};
