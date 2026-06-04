<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('static_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status')->default('pending');
            $table->text('locale_set')->default('["en"]');
            $table->boolean('include_api_bridge')->default(true);
            $table->string('zip_path')->nullable();
            $table->integer('zip_size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('static_exports');
    }
};
