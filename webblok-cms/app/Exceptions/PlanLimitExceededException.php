<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class PlanLimitExceededException extends RuntimeException
{
    public static function forResource(string $resource, int $limit): self
    {
        return new self("Plan limit reached for {$resource} (max {$limit}). Upgrade to continue.");
    }
}
