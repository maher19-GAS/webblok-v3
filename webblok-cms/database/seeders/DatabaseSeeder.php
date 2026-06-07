<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the platform database.
     *
     * Platform bootstrap data is delegated to dedicated seeders so the order of
     * dependent records (plans -> super admin -> blok definitions -> marketplace)
     * is explicit and reproducible.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            SuperAdminSeeder::class,
            BlokDefinitionSeeder::class,
            MarketplaceSeeder::class,
        ]);
    }
}
