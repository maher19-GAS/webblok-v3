<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('site_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->string('cast_type')->default('string');
            $table->string('group_name')->nullable();
        });

        $defaults = [
            ['key' => 'site_name', 'value' => 'My Website', 'cast_type' => 'string', 'group_name' => 'general'],
            ['key' => 'default_locale', 'value' => 'en', 'cast_type' => 'string', 'group_name' => 'general'],
            ['key' => 'supported_locales', 'value' => '["en"]', 'cast_type' => 'json', 'group_name' => 'general'],
            ['key' => 'logo_media_id', 'value' => null, 'cast_type' => 'string', 'group_name' => 'branding'],
            ['key' => 'favicon_media_id', 'value' => null, 'cast_type' => 'string', 'group_name' => 'branding'],
            ['key' => 'primary_color', 'value' => '#6366f1', 'cast_type' => 'string', 'group_name' => 'branding'],
            ['key' => 'active_theme_slug', 'value' => null, 'cast_type' => 'string', 'group_name' => 'theme'],
            ['key' => 'google_analytics_id', 'value' => null, 'cast_type' => 'string', 'group_name' => 'analytics'],
            ['key' => 'footer_text', 'value' => '', 'cast_type' => 'string', 'group_name' => 'general'],
            ['key' => 'robots_txt', 'value' => "User-agent: *\nAllow: /", 'cast_type' => 'string', 'group_name' => 'seo'],
        ];

        DB::connection('tenant')->table('site_settings')->insert($defaults);
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('site_settings');
    }
};
