<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * WebBlok Site Bundle v1 — the open, portable site format.
 * Used for: templates, AI generation output, import/export, dormant snapshots.
 */
final class SiteBundleData extends Data
{
    /**
     * @param  list<string>  $locales
     * @param  list<SiteBundlePageData>  $pages
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $bundleVersion,
        public string $name,
        public string $defaultLocale,
        public array $locales,
        public ?string $themeSlug,
        public array $pages,
        public array $settings = [],
    ) {}
}
