<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('page_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('page_id');
            $table->integer('revision_number');
            $table->text('snapshot');
            $table->text('change_summary')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['page_id', 'revision_number']);

            $table->index('page_id', 'idx_revisions_page');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('page_revisions');
    }
};
