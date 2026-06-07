<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class SiteBundlePageData extends Data
{
    /**
     * @param  array<string, array<string, string|null>>  $locales
     * @param  array<string, list<SiteBundleBlokData>>  $sections
     */
    public function __construct(
        public string $slug,
        public bool $isHomepage,
        public string $template,
        public array $locales,
        public array $sections,
    ) {}
}
