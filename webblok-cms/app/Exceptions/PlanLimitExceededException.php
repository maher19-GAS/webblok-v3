<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PlanLimitExceededException extends RuntimeException
{
    public static function forResource(string $resource, int $limit): self
    {
        return new self("Plan limit reached for {$resource} (max {$limit}). Upgrade to continue.");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['error' => $this->getMessage()], 429);
    }
}
