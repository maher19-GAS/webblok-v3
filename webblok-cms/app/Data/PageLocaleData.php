<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class PageLocaleData extends Data
{
    public function __construct(
        public string $locale,
        public string $title,
        public ?string $description = null,
        public ?string $content = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public bool $isIndexable = true,
    ) {}
}
