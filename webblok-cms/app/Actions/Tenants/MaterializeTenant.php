<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Data\SiteBundleData;
use App\Enums\TenantStatus;
use App\Models\Platform\Tenant;
use App\Services\SiteBundleImporter;
use App\Services\TenantMigrator;

final class MaterializeTenant
{
    public function __construct(
        private readonly TenantMigrator $migrator,
        private readonly SiteBundleImporter $importer,
    ) {}

    /**
     * Converts a DORMANT tenant (stored only as a compressed snapshot)
     * into a live SQLite-backed tenant on first write / first traffic.
     */
    public function execute(Tenant $tenant): void
    {
        if ($tenant->status !== TenantStatus::DORMANT) {
            return; // already materialized — idempotent
        }

        // 1. Create SQLite file + run all tenant migrations
        $this->migrator->provision($tenant);

        // 2. If a dormant snapshot exists, hydrate the DB from it
        /** @var array<string, mixed> $settings */
        $settings = $tenant->settings ?? [];

        if (isset($settings['snapshot']) && is_string($settings['snapshot'])) {
            $decoded = base64_decode($settings['snapshot'], true);
            $json = $decoded === false ? '' : (string) gzdecode($decoded);

            /** @var array<string, mixed> $payload */
            $payload = (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $bundle = SiteBundleData::from($payload);
            $this->importer->import($bundle);

            unset($settings['snapshot']);
            $tenant->settings = $settings;
        }

        $tenant->status = TenantStatus::ACTIVE;
        $tenant->save();
    }
}
