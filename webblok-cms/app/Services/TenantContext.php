<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Platform\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Holds the "current" tenant for the request lifecycle and reconfigures the
 * `tenant` database connection to point at the active tenant's SQLite file.
 */
final class TenantContext
{
    private ?Tenant $current = null;

    public function switchTo(Tenant $tenant): void
    {
        $this->current = $tenant;

        Config::set('database.connections.tenant.database', $tenant->database_path);

        DB::purge('tenant');
        DB::reconnect('tenant');

        $conn = DB::connection('tenant');
        $conn->statement('PRAGMA journal_mode = WAL');
        $conn->statement('PRAGMA synchronous = NORMAL');
        $conn->statement('PRAGMA foreign_keys = ON');
        $conn->statement('PRAGMA busy_timeout = 5000');
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function hasTenant(): bool
    {
        return $this->current !== null;
    }

    public function forget(): void
    {
        $this->current = null;
        DB::purge('tenant');
    }
}
