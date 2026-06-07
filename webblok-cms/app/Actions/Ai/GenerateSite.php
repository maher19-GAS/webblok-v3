<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Data\SiteBundleData;
use App\Services\Ai\Contracts\SiteGeneratorDriver;

final class GenerateSite
{
    public function __construct(
        private readonly SiteGeneratorDriver $driver,
    ) {}

    /**
     * @param  list<string>  $locales
     */
    public function execute(
        string $prompt,
        array $locales = ['en'],
        ?string $logoMediaId = null,
    ): SiteBundleData {
        return $this->driver->generate($prompt, $locales, $logoMediaId);
    }
}
