<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MarketplaceItemType;
use App\Exceptions\MarketplaceInstallException;
use App\Models\Platform\MarketplaceItem;
use App\Models\Platform\Tenant;
use App\Models\Tenant\TenantTemplate;
use App\Models\Tenant\TenantTheme;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Browse + install marketplace items (themes and templates). Theme installs
 * activate one theme per tenant; template installs register the template so it
 * can be applied to new pages.
 */
final class MarketplaceService
{
    /**
     * @return Collection<int, MarketplaceItem>
     */
    public function browse(?MarketplaceItemType $type = null): Collection
    {
        $query = MarketplaceItem::query()->where('is_active', true);

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        /** @var Collection<int, MarketplaceItem> $items */
        $items = $query->orderByDesc('is_featured')->orderBy('name')->get();

        return $items;
    }

    public function install(Tenant $tenant, MarketplaceItem $item): void
    {
        // Register install on the platform side (idempotent unique constraint).
        DB::table('tenant_marketplace_installs')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'item_id' => $item->id],
            ['id' => (string) Str::uuid(), 'installed_at' => now()->toISOString(), 'is_active' => true],
        );

        match ($item->type) {
            MarketplaceItemType::THEME => $this->installTheme($item),
            MarketplaceItemType::TEMPLATE => $this->installTemplate($item),
        };

        $item->forceFill(['install_count' => $item->install_count + 1])->save();
    }

    private function installTheme(MarketplaceItem $item): void
    {
        // Only one active theme per tenant.
        TenantTheme::query()->update(['is_active' => false]);

        TenantTheme::query()->updateOrCreate(
            ['item_slug' => $item->slug],
            [
                'id' => (string) Str::uuid(),
                'is_active' => true,
                'custom_vars' => null,
                'installed_at' => now()->toISOString(),
            ],
        );
    }

    private function installTemplate(MarketplaceItem $item): void
    {
        if ($item->local_path === null) {
            throw new MarketplaceInstallException("Template '{$item->slug}' has no installable payload.");
        }

        TenantTemplate::query()->updateOrCreate(
            ['item_slug' => $item->slug],
            [
                'id' => (string) Str::uuid(),
                'name' => $item->name,
                'installed_at' => now()->toISOString(),
            ],
        );
    }
}
