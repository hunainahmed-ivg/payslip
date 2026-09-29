<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;

class RolePermissions
{
    /** @return list<string> */
    public static function forEmployee(): array
    {
        return [
            Permission::DashboardView->value,
            Permission::PortalPayslips->value,
        ];
    }

    /** @return list<string> */
    public static function forAdmin(User $user): array
    {
        $permissions = Permission::values();

        if ($user->company_id !== null) {
            $permissions = array_values(array_filter(
                $permissions,
                fn (string $permission) => $permission !== Permission::CompaniesManage->value,
            ));
        }

        return $permissions;
    }

    /** @return list<string> */
    public static function forUser(User $user): array
    {
        if (is_array($user->assigned_permissions)) {
            return array_values(array_unique(array_filter($user->assigned_permissions)));
        }

        if ($user->isAdmin()) {
            return self::forAdmin($user);
        }

        if ($user->isEmployee()) {
            return self::forEmployee();
        }

        return self::forEmployee();
    }

    public static function roleLabel(User $user): string
    {
        if ($user->isAdmin()) {
            return UserRole::Admin->label();
        }

        if ($user->isEmployee()) {
            return UserRole::Employee->label();
        }

        return ucfirst((string) $user->role);
    }

    /** @return list<string> */
    public static function defaultsForRole(string $role, ?int $companyId = null): array
    {
        $stub = new User([
            'role' => $role,
            'company_id' => $companyId,
        ]);

        if (in_array(strtolower($role), ['admin', 'hr', 'super-admin', 'administrator'], true)) {
            return self::forAdmin($stub);
        }

        return self::forEmployee();
    }
}
