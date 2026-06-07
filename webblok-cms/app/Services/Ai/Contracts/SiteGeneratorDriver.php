<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Data\SiteBundleData;

interface SiteGeneratorDriver
{
    /**
     * @param  list<string>  $locales
     */
    public function generate(string $prompt, array $locales, ?string $logoMediaId): SiteBundleData;
}
