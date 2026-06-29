<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->integer('max_pages')->default(5);
            $table->integer('max_bloks')->default(50);
            $table->integer('max_locales')->default(1);
            $table->integer('max_media_mb')->default(100);
            $table->integer('max_exports')->default(1);
            $table->integer('max_api_rpm')->default(60);
            $table->float('price_monthly')->default(0.0);
            $table->float('price_yearly')->default(0.0);
            $table->text('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
