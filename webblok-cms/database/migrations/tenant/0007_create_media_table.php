<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('collection_name')->default('default');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type');
            $table->string('disk')->default('tenant');
            $table->integer('size')->default(0);
            $table->text('manipulations')->default('{}');
            $table->text('custom_properties')->default('{}');
            $table->text('responsive_images')->default('{}');
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('media');
    }
};
