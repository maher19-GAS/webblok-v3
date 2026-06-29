<?php

declare(strict_types=1);

namespace App\Actions\Bloks;

use App\Models\Tenant\BlokInstance;

final class UpdateBlokInstance
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, array<string, mixed>>  $localeConfig
     */
    public function execute(string $instanceId, array $config, array $localeConfig = []): BlokInstance
    {
        /** @var BlokInstance $instance */
        $instance = BlokInstance::query()->findOrFail($instanceId);

        $instance->config = $config;
        $instance->locale_config = $localeConfig === [] ? $instance->locale_config : $localeConfig;
        $instance->updated_at = now();
        $instance->save();

        return $instance;
    }
}
