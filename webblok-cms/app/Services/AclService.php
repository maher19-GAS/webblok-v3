<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Platform\User;

/**
 * Minimal role/permission helper for the platform side. Tenant-level granular
 * permissions are handled inside the tenant database via spatie/laravel-permission;
 * this service governs the platform roles (super_admin, tenant_owner, etc.).
 */
final class AclService
{
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_TENANT_OWNER = 'tenant_owner';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_VIEWER = 'viewer';

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return [self::ROLE_SUPER_ADMIN, self::ROLE_TENANT_OWNER, self::ROLE_EDITOR, self::ROLE_VIEWER];
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->role === self::ROLE_SUPER_ADMIN;
    }

    public function canManageTenant(User $user, string $ownerId): bool
    {
        return $this->isSuperAdmin($user) || $user->id === $ownerId;
    }

    /**
     * Whether $role satisfies $required using a simple ladder where
     * super_admin > tenant_owner > editor > viewer.
     */
    public function roleSatisfies(string $role, string $required): bool
    {
        $ladder = [
            self::ROLE_VIEWER => 1,
            self::ROLE_EDITOR => 2,
            self::ROLE_TENANT_OWNER => 3,
            self::ROLE_SUPER_ADMIN => 4,
        ];

        $have = $ladder[$role] ?? 0;
        $need = $ladder[$required] ?? 0;

        return $have >= $need;
    }
}
