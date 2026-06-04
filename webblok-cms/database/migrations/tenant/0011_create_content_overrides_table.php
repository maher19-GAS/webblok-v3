<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('content_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('blok_key');
            $table->string('lang');
            $table->string('key_path');
            $table->text('value');
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['blok_key', 'lang', 'key_path']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('content_overrides');
    }
};
