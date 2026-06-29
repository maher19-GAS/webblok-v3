<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('source')->default('official');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('thumbnail_url')->nullable();
            $table->string('preview_url')->nullable();
            $table->string('download_url')->nullable();
            $table->string('local_path')->nullable();
            $table->text('tags')->nullable();
            $table->string('category')->nullable();
            $table->text('locale_support')->default('["en"]');
            $table->float('price')->default(0.0);
            $table->integer('install_count')->default(0);
            $table->float('rating')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_items');
    }
};
