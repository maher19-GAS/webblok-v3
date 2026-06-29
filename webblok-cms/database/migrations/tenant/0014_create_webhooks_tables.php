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

        $schema->create('webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('url');
            $table->text('events')->default('[]');
            $table->string('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('last_status_code')->nullable();
            $table->timestamps();
        });

        $schema->create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('webhook_id');
            $table->string('event');
            $table->text('payload');
            $table->integer('status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('webhook_deliveries');
        $schema->dropIfExists('webhooks');
    }
};
