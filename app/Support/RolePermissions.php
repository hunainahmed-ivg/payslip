<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;

class RolePermissions
{
    /**
     * Permissions reserved for Company Admin / Super Admin.
     * Employees must never receive these — even via custom permission overrides.
     *
     * @return list<string>
     */
    public static function adminOnlyPermissions(): array
    {
        return [
            Permission::UsersManage->value,
            Permission::EmployeesManage->value,
            Permission::CompaniesManage->value,
            Permission::DashboardAdminStats->value,
        ];
    }

    /** @return list<string> */
    public static function forEmployee(): array
    {
        return [
            Permission::DashboardView->value,
            Permission::PortalPayslips->value,
        ];
    }

    /**
     * Pages a Company Admin may optionally grant to an employee (custom access).
     * Defaults stay portal-only; admin-only pages are never included.
     *
     * @return list<string>
     */
    public static function assignableToEmployee(): array
    {
        $blocked = self::adminOnlyPermissions();

        return array_values(array_filter(
            Permission::values(),
            fn (string $permission) => ! in_array($permission, $blocked, true),
        ));
    }

    /** @return list<string> */
    public static function forCompanyAdmin(): array
    {
        return array_values(array_filter(
            Permission::values(),
            fn (string $permission) => $permission !== Permission::CompaniesManage->value,
        ));
    }

    /** @return list<string> */
    public static function forSuperAdmin(): array
    {
        return Permission::values();
    }

    /**
     * Strip privileges that the given role is never allowed to hold.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function sanitizeForRole(string $role, array $permissions, ?int $companyId = null): array
    {
        $permissions = array_values(array_unique(array_filter($permissions)));

        $isSuperAdmin = UserRole::isSuperAdminRole($role)
            || ($role === UserRole::LEGACY_ADMIN && $companyId === null);

        if ($isSuperAdmin) {
            return $permissions;
        }

        // Platform-only: never on company-scoped accounts.
        $permissions = array_values(array_filter(
            $permissions,
            fn (string $permission) => $permission !== Permission::CompaniesManage->value,
        ));

        $isAdmin = UserRole::isAdminRole($role);
        if (! $isAdmin) {
            $blocked = self::adminOnlyPermissions();
            $permissions = array_values(array_filter(
                $permissions,
                fn (string $permission) => ! in_array($permission, $blocked, true),
            ));
        }

        return $permissions;
    }

    /** @return list<string> */
    public static function forUser(User $user): array
    {
        if (is_array($user->assigned_permissions)) {
            return self::sanitizeForRole(
                (string) $user->role,
                $user->assigned_permissions,
                $user->company_id,
            );
        }

        return self::defaultsForRole(
            (string) $user->role,
            $user->company_id,
        );
    }

    public static function roleLabel(User $user): string
    {
        if ($user->isSuperAdmin()) {
            return UserRole::SuperAdmin->label();
        }

        if ($user->isCompanyAdmin()) {
            return UserRole::CompanyAdmin->label();
        }

        if ($user->isEmployee()) {
            return UserRole::Employee->label();
        }

        $resolved = UserRole::tryFromFlexible($user->role);

        return $resolved?->label() ?? ucfirst((string) $user->role);
    }

    /** @return list<string> */
    public static function defaultsForRole(string $role, ?int $companyId = null): array
    {
        if (UserRole::isSuperAdminRole($role) || ($role === UserRole::LEGACY_ADMIN && $companyId === null)) {
            return self::forSuperAdmin();
        }

        if (UserRole::isAdminRole($role)) {
            return self::forCompanyAdmin();
        }

        return self::forEmployee();
    }

    /**
     * Roles the actor is allowed to assign in Users & Access.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function assignableRolesFor(User $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return [
                ['value' => UserRole::CompanyAdmin->value, 'label' => UserRole::CompanyAdmin->label()],
                ['value' => UserRole::Employee->value, 'label' => UserRole::Employee->label()],
                ['value' => UserRole::SuperAdmin->value, 'label' => UserRole::SuperAdmin->label()],
            ];
        }

        if ($actor->isCompanyAdmin()) {
            return [
                ['value' => UserRole::Employee->value, 'label' => UserRole::Employee->label()],
            ];
        }

        return [];
    }
}
