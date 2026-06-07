<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class BlokSchemaStepData extends Data
{
    /**
     * @param  DataCollection<int, BlokSchemaFieldData>  $fields
     */
    public function __construct(
        public string $title,
        #[DataCollectionOf(BlokSchemaFieldData::class)]
        public DataCollection $fields,
        public ?string $description = null,
    ) {}
}
