<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('blok_grants', function (Blueprint $table): void {
            $table->id();
            $table->string('blok_key')->unique();
            $table->boolean('is_enabled')->default(true);
            $table->text('custom_data')->nullable();
            $table->timestamp('granted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('blok_grants');
    }
};
