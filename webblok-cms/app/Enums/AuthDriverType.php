<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthDriverType: string
{
    case BREEZE = 'breeze';
    case GAS = 'gas';

    public static function fromConfig(): self
    {
        $driver = config('auth_driver.driver', 'breeze');

        return self::from(is_string($driver) ? $driver : 'breeze');
    }
}
