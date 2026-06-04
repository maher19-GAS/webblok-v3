<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('blok_instances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('page_id');
            $table->uuid('parent_id')->nullable();
            $table->string('slot_name')->nullable();
            $table->string('blok_key');
            $table->string('section')->default('body');
            $table->text('config')->default('{}');
            $table->text('locale_config')->default('{}');
            $table->boolean('is_visible')->default(true);
            $table->string('required_role')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('css_classes')->nullable();
            $table->string('animation')->nullable();
            $table->string('anchor_id')->nullable();
            $table->timestamps();

            $table->index('page_id', 'idx_blok_inst_page');
            $table->index('parent_id', 'idx_blok_inst_parent');
            $table->index(['section', 'sort_order'], 'idx_blok_inst_section');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('blok_instances');
    }
};
