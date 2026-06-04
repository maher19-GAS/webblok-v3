<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('page_views', function (Blueprint $table): void {
            $table->id();
            $table->uuid('page_id');
            $table->string('locale')->default('en');
            $table->string('referrer')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('country_code')->nullable();
            $table->timestamp('viewed_at');

            $table->index(['page_id', 'viewed_at'], 'idx_page_views_page');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('page_views');
    }
};
