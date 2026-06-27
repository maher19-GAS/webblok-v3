<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('author_id');
            $table->uuid('author_tenant_id')->nullable();
            $table->string('type'); // blok, template, site, theme
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->text('payload'); // JSON: BlokDefinition | SiteBundle | theme manifest
            $table->string('thumbnail_url')->nullable();
            $table->text('tags')->nullable();
            $table->string('category')->nullable();
            $table->text('locale_support')->default('["en"]');
            $table->string('license')->default('free');
            $table->float('price')->default(0.0);
            $table->string('status')->default('draft');
            $table->text('review_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->integer('install_count')->default(0);
            $table->integer('rating_sum')->default(0);
            $table->integer('rating_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->timestamp('published_at')->nullable();

            $table->index(['status', 'type']);
            $table->index('author_id');
        });

        Schema::create('community_ratings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('artifact_id');
            $table->uuid('user_id');
            $table->integer('stars');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['artifact_id', 'user_id']);
        });

        Schema::create('community_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('artifact_id');
            $table->uuid('reporter_id');
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_reports');
        Schema::dropIfExists('community_ratings');
        Schema::dropIfExists('community_artifacts');
    }
};
