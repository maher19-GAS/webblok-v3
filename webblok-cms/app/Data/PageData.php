<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PageStatus;
use Spatie\LaravelData\Data;

final class PageData extends Data
{
    /**
     * @param  array<int, PageLocaleData>  $locales
     * @param  array<int, BlokInstanceData>  $bloks
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $fullPath,
        public PageStatus $status,
        public string $template = 'default',
        public bool $isHomepage = false,
        public ?string $parentId = null,
        public int $sortOrder = 0,
        public array $locales = [],
        public array $bloks = [],
    ) {}
}
