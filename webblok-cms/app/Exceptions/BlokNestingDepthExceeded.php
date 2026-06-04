<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class BlokNestingDepthExceeded extends RuntimeException
{
    public static function forDepth(int $depth, int $max): self
    {
        return new self("Blok nesting depth {$depth} exceeds maximum of {$max}.");
    }
}
