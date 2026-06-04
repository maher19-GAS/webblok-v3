<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tenant')->statement(
            'CREATE VIRTUAL TABLE IF NOT EXISTS pages_fts USING fts5('
            .'page_id UNINDEXED, locale UNINDEXED, title, content, '
            ."tokenize = 'porter ascii')"
        );
    }

    public function down(): void
    {
        DB::connection('tenant')->statement('DROP TABLE IF EXISTS pages_fts');
    }
};
