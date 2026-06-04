<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Contracts\AuthDriverInterface;
use App\Auth\Drivers\BreezeAuthDriver;
use App\Auth\Drivers\GasAuthDriver;
use App\Enums\AuthDriverType;
use Illuminate\Contracts\Container\Container;

final class AuthManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function driver(): AuthDriverInterface
    {
        return match (AuthDriverType::fromConfig()) {
            AuthDriverType::BREEZE => $this->container->make(BreezeAuthDriver::class),
            AuthDriverType::GAS => $this->container->make(GasAuthDriver::class),
        };
    }
}
