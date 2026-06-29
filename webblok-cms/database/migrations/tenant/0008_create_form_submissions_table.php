<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('form_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('blok_instance_id');
            $table->uuid('page_id');
            $table->string('locale')->default('en');
            $table->text('data');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('form_submissions');
    }
};
