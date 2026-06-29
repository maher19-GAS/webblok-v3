<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case PROVISIONING = 'provisioning';
    case DORMANT = 'dormant';      // no SQLite file yet; stored as compressed snapshot
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DELETED = 'deleted';

    public function isLive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function needsMaterialization(): bool
    {
        return $this === self::DORMANT;
    }

    public function label(): string
    {
        return match ($this) {
            self::PROVISIONING => 'Provisioning',
            self::DORMANT => 'Dormant',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::DELETED => 'Deleted',
        };
    }
}
