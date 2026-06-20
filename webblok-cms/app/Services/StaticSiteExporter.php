<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExportStatus;
use App\Enums\PageStatus;
use App\Models\Platform\Tenant;
use App\Models\Tenant\Page;
use App\Models\Tenant\StaticExport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Produces a portable static export of a tenant site: one HTML file per page
 * per locale, plus sitemap.xml and (optionally) the api-client.js bridge so the
 * exported site can still pull live blok data from the V1 API.
 */
final class StaticSiteExporter
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly SitemapGenerator $sitemap,
        private readonly LocaleService $locales,
    ) {}

    /**
     * @param  list<string>  $localeSet
     */
    public function export(Tenant $tenant, array $localeSet, bool $includeApiBridge = true): StaticExport
    {
        $record = StaticExport::query()->create([
            'id' => (string) Str::uuid(),
            'status' => ExportStatus::BUILDING->value,
            'locale_set' => $localeSet === [] ? $this->locales->codes() : $localeSet,
            'include_api_bridge' => $includeApiBridge,
            'started_at' => now()->toISOString(),
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        try {
            $zipPath = $this->build($tenant, $record, $includeApiBridge);
            $size = File::exists($zipPath) ? File::size($zipPath) : 0;

            $ttlRaw = config('webblok.export.ttl_hours');
            $ttlHours = is_numeric($ttlRaw) ? (int) $ttlRaw : 48;

            $record->forceFill([
                'status' => ExportStatus::COMPLETE->value,
                'zip_path' => $zipPath,
                'zip_size' => $size,
                'completed_at' => now()->toISOString(),
                'expires_at' => now()->addHours($ttlHours)->toISOString(),
            ])->save();
        } catch (Throwable $e) {
            $record->forceFill([
                'status' => ExportStatus::FAILED->value,
                'error_message' => $e->getMessage(),
                'completed_at' => now()->toISOString(),
            ])->save();
        }

        return $record;
    }

    private function build(Tenant $tenant, StaticExport $record, bool $includeApiBridge): string
    {
        /** @var list<string> $localeSet */
        $localeSet = $record->locale_set;

        $workDir = storage_path("tenants/{$tenant->slug}/exports/{$record->id}");
        File::ensureDirectoryExists($workDir);

        /** @var Collection<int, Page> $pages */
        $pages = Page::query()
            ->where('status', PageStatus::PUBLISHED->value)
            ->orderBy('full_path')
            ->get();

        foreach ($localeSet as $locale) {
            foreach ($pages as $page) {
                $localeRecord = $page->locale($locale);
                $title = $localeRecord !== null ? $localeRecord->title : $tenant->name;
                $html = $this->pageRenderer->renderDocument($page, $locale, $title);

                $relative = $page->is_homepage ? 'index' : ltrim($page->full_path, '/');
                $fileName = "{$locale}/{$relative}.html";
                $fullPath = "{$workDir}/{$fileName}";
                File::ensureDirectoryExists(dirname($fullPath));
                File::put($fullPath, $html);
            }
        }

        $baseDomainRaw = config('webblok.base_domain');
        $baseDomain = is_string($baseDomainRaw) ? $baseDomainRaw : 'webblok.io';
        $baseUrl = $tenant->domain !== null
            ? 'https://'.$tenant->domain
            : 'https://'.$tenant->subdomain.'.'.$baseDomain;
        File::put("{$workDir}/sitemap.xml", $this->sitemap->generate($baseUrl));

        if ($includeApiBridge) {
            $bridge = public_path('js/api-client.js');
            if (File::exists($bridge)) {
                File::ensureDirectoryExists("{$workDir}/js");
                File::copy($bridge, "{$workDir}/js/api-client.js");
            }
        }

        return $this->zip($workDir, storage_path("tenants/{$tenant->slug}/exports/{$record->id}.zip"));
    }

    private function zip(string $sourceDir, string $zipPath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Unable to create archive at {$zipPath}.");
        }

        $files = File::allFiles($sourceDir);
        foreach ($files as $file) {
            $local = str_replace($sourceDir.'/', '', $file->getPathname());
            $zip->addFile($file->getPathname(), $local);
        }

        $zip->close();

        return $zipPath;
    }
}
