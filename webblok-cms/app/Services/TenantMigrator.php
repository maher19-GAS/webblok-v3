<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\Platform\Tenant;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Provisions a tenant SQLite database and runs the tenant migrations without
 * requiring SSH / Artisan on the server. Migrations are plain anonymous-class
 * files executed against the active `tenant` connection.
 */
final class TenantMigrator
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly TenantContext $tenantContext,
    ) {}

    public function provision(Tenant $tenant): void
    {
        // 1. Create directory structure
        $tenantDir = storage_path("tenants/{$tenant->slug}");
        File::makeDirectory("{$tenantDir}/media", 0755, true, true);
        File::makeDirectory("{$tenantDir}/exports", 0755, true, true);

        // 2. Create SQLite file
        $dbPath = "{$tenantDir}/database.sqlite";
        if (! file_exists($dbPath)) {
            touch($dbPath);
        }

        $tenant->update([
            'database_path' => $dbPath,
            'storage_path' => $tenantDir,
        ]);

        // 3. Switch tenant connection (also applies WAL pragmas)
        $this->tenantContext->switchTo($tenant);

        // 4. Run all tenant migration files directly (no Artisan CLI)
        $this->runMigrations();

        // 5. Seed default roles, menu, and site settings
        $this->seedDefaults();

        // 6. Mark active
        $tenant->update(['status' => TenantStatus::ACTIVE->value]);
    }

    private function runMigrations(): void
    {
        $migrationPath = database_path('migrations/tenant');
        $migrationFiles = glob("{$migrationPath}/*.php");

        if ($migrationFiles === false) {
            return;
        }

        sort($migrationFiles);

        foreach ($migrationFiles as $file) {
            $migration = require $file;

            if ($migration instanceof Migration && method_exists($migration, 'up')) {
                /** @var callable(): void $up */
                $up = [$migration, 'up'];
                $up();
            }
        }
    }

    private function seedDefaults(): void
    {
        $conn = $this->db->connection('tenant');

        foreach (['admin', 'editor', 'viewer'] as $role) {
            $conn->table('roles')->insert([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ]);
        }

        $conn->table('menus')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Main Navigation',
            'handle' => 'main-nav',
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);
    }
}
