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

        $schema->create('menus', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('handle')->unique();
            $table->timestamps();
        });

        $schema->create('menu_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('menu_id');
            $table->uuid('parent_id')->nullable();
            $table->text('label');
            $table->string('type')->default('page');
            $table->uuid('page_id')->nullable();
            $table->string('url')->nullable();
            $table->string('target')->default('_self');
            $table->string('icon')->nullable();
            $table->string('required_role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('menu_id', 'idx_menu_items_menu');
            $table->index('parent_id', 'idx_menu_items_parent');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('menu_items');
        $schema->dropIfExists('menus');
    }
};
