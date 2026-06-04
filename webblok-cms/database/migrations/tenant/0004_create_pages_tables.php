<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        $schema->create('pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('slug');
            $table->string('full_path');
            $table->string('status')->default('draft');
            $table->string('template')->default('default');
            $table->boolean('is_homepage')->default(false);
            $table->boolean('requires_auth')->default(false);
            $table->string('required_role')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['slug', 'parent_id']);

            $table->index('status', 'idx_pages_status');
            $table->index('full_path', 'idx_pages_path');
        });

        $schema->create('page_locales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('page_id');
            $table->string('locale');
            $table->string('title')->default('');
            $table->text('description')->nullable();
            $table->text('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->uuid('og_image_id')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->timestamps();
            $table->unique(['page_id', 'locale']);

            $table->index('page_id', 'idx_page_locales_page');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('page_locales');
        $schema->dropIfExists('pages');
    }
};
