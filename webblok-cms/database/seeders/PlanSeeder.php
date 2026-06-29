<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Platform\Plan;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'free',
                'display_name' => 'Free',
                'max_pages' => 5,
                'max_bloks' => 50,
                'max_locales' => 1,
                'max_media_mb' => 100,
                'max_exports' => 1,
                'max_api_rpm' => 60,
                'price_monthly' => 0.0,
                'price_yearly' => 0.0,
                'features' => ['static_export' => true, 'custom_domain' => false, 'api_v2' => false],
                'is_active' => true,
            ],
            [
                'name' => 'starter',
                'display_name' => 'Starter',
                'max_pages' => 25,
                'max_bloks' => 250,
                'max_locales' => 3,
                'max_media_mb' => 1024,
                'max_exports' => 10,
                'max_api_rpm' => 120,
                'price_monthly' => 12.0,
                'price_yearly' => 120.0,
                'features' => ['static_export' => true, 'custom_domain' => true, 'api_v2' => true],
                'is_active' => true,
            ],
            [
                'name' => 'pro',
                'display_name' => 'Pro',
                'max_pages' => 100,
                'max_bloks' => 2000,
                'max_locales' => 10,
                'max_media_mb' => 10240,
                'max_exports' => 50,
                'max_api_rpm' => 600,
                'price_monthly' => 39.0,
                'price_yearly' => 390.0,
                'features' => ['static_export' => true, 'custom_domain' => true, 'api_v2' => true, 'ai_generation' => true],
                'is_active' => true,
            ],
            [
                'name' => 'business',
                'display_name' => 'Business',
                'max_pages' => 1000,
                'max_bloks' => 20000,
                'max_locales' => 25,
                'max_media_mb' => 102400,
                'max_exports' => 500,
                'max_api_rpm' => 3000,
                'price_monthly' => 99.0,
                'price_yearly' => 990.0,
                'features' => ['static_export' => true, 'custom_domain' => true, 'api_v2' => true, 'ai_generation' => true, 'priority_support' => true],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
