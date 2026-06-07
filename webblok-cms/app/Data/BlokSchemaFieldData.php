<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class BlokSchemaFieldData extends Data
{
    /**
     * @param  array<int, array{value: string, label: string}>|Optional  $options
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type,
        public bool $required = false,
        public bool $translatable = false,
        public mixed $default = null,
        public string|Optional $help = new Optional,
        public string|Optional $placeholder = new Optional,
        public array|Optional $options = new Optional,
    ) {}
}
