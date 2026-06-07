<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class BlokInstanceData extends Data
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, array<string, mixed>>  $localeConfig
     * @param  array<int, BlokInstanceData>  $children
     */
    public function __construct(
        public string $id,
        public string $blokKey,
        public string $section,
        public array $config = [],
        public array $localeConfig = [],
        public ?string $parentId = null,
        public ?string $slotName = null,
        public bool $isVisible = true,
        public int $sortOrder = 0,
        public ?string $cssClasses = null,
        public ?string $anchorId = null,
        public array $children = [],
    ) {}
}
