<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class BlokSchemaData extends Data
{
    /**
     * @param  DataCollection<int, BlokSchemaStepData>  $steps
     */
    public function __construct(
        #[DataCollectionOf(BlokSchemaStepData::class)]
        public DataCollection $steps,
    ) {}
}
