<?php

declare(strict_types=1);

namespace App\Enums;

enum ExportStatus: string
{
    case PENDING = 'pending';
    case BUILDING = 'building';
    case COMPLETE = 'complete';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::BUILDING => 'Building',
            self::COMPLETE => 'Complete',
            self::FAILED => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::COMPLETE || $this === self::FAILED;
    }
}
