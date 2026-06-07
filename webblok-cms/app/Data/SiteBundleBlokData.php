<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class SiteBundleBlokData extends Data
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, array<string, mixed>>  $localeConfig
     * @param  list<SiteBundleBlokData>  $children
     */
    public function __construct(
        public string $blokKey,
        public int $sortOrder,
        public array $config = [],
        public array $localeConfig = [],
        public ?string $slotName = null,
        public array $children = [],
    ) {}
}
