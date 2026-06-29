<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\SiteBundleBlokData;
use App\Data\SiteBundleData;
use App\Data\SiteBundlePageData;
use App\Models\Tenant\BlokInstance;
use App\Models\Tenant\Page;
use App\Models\Tenant\PageLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Installs any portable Site Bundle into the currently active tenant.
 * Used by templates, AI generation, import/export, and dormant materialization.
 */
final class SiteBundleImporter
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function import(SiteBundleData $bundle): void
    {
        $this->tenantContext->require();

        DB::connection('tenant')->transaction(function () use ($bundle): void {
            foreach ($bundle->pages as $page) {
                $this->importPage($page);
            }

            if ($bundle->themeSlug !== null) {
                DB::connection('tenant')->table('site_settings')
                    ->updateOrInsert(
                        ['key' => 'active_theme_slug'],
                        ['value' => $bundle->themeSlug, 'cast_type' => 'string', 'group_name' => 'theme'],
                    );
            }
        });
    }

    private function importPage(SiteBundlePageData $page): void
    {
        $pageId = (string) Str::uuid();

        Page::query()->create([
            'id' => $pageId,
            'slug' => $page->slug,
            'full_path' => '/'.ltrim($page->slug, '/'),
            'status' => 'draft',
            'template' => $page->template,
            'is_homepage' => $page->isHomepage,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        foreach ($page->locales as $locale => $fields) {
            PageLocale::query()->create([
                'id' => (string) Str::uuid(),
                'page_id' => $pageId,
                'locale' => $locale,
                'title' => $fields['title'] ?? '',
                'meta_title' => $fields['meta_title'] ?? null,
                'meta_description' => $fields['meta_description'] ?? null,
                'is_indexable' => true,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ]);
        }

        foreach ($page->sections as $section => $bloks) {
            foreach ($bloks as $blok) {
                $this->importBlok($pageId, $section, $blok, null);
            }
        }
    }

    private function importBlok(string $pageId, string $section, SiteBundleBlokData $blok, ?string $parentId): void
    {
        $id = (string) Str::uuid();

        BlokInstance::query()->create([
            'id' => $id,
            'page_id' => $pageId,
            'parent_id' => $parentId,
            'slot_name' => $blok->slotName,
            'blok_key' => $blok->blokKey,
            'section' => $section,
            'config' => json_encode($blok->config, JSON_THROW_ON_ERROR),
            'locale_config' => json_encode($blok->localeConfig, JSON_THROW_ON_ERROR),
            'sort_order' => $blok->sortOrder,
            'is_visible' => true,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        foreach ($blok->children as $child) {
            $this->importBlok($pageId, $section, $child, $id);
        }
    }
}
