<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blok_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('blok_key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('category')->default('content');
            $table->integer('chapter')->nullable();
            $table->integer('volume')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('accepts_children')->default(false);
            $table->text('slots')->nullable();
            $table->text('schema')->default('{}');
            $table->text('default_config')->nullable();
            $table->string('blade_component');
            $table->text('langs')->default('["en"]');
            $table->boolean('has_skeleton')->default(true);
            $table->boolean('has_interactive')->default(false);
            $table->boolean('cdn_ready')->default(false);
            $table->string('source_file')->nullable();
            $table->text('preview_html')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('tags')->nullable();
            $table->timestamps();

            $table->index('category', 'idx_blok_defs_category');
            $table->index('is_active', 'idx_blok_defs_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blok_definitions');
    }
};
