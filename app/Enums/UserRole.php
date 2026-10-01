<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case CompanyAdmin = 'company_admin';
    case Employee = 'employee';

    /** Legacy role stored before the hierarchy rename. */
    public const LEGACY_ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::CompanyAdmin => 'Company Admin',
            self::Employee => 'Employee',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }

    public static function tryFromFlexible(?string $role): ?self
    {
        $normalized = strtolower(trim((string) $role));

        return match ($normalized) {
            self::SuperAdmin->value, 'super-admin', 'platform_admin', 'platform-admin' => self::SuperAdmin,
            self::CompanyAdmin->value, 'company-admin', 'tenant_admin', 'tenant-admin',
            self::LEGACY_ADMIN, 'hr', 'administrator' => self::CompanyAdmin,
            self::Employee->value => self::Employee,
            default => self::tryFrom($normalized),
        };
    }

    public static function isSuperAdminRole(?string $role): bool
    {
        $normalized = strtolower(trim((string) $role));

        return in_array($normalized, [
            self::SuperAdmin->value,
            'super-admin',
            'platform_admin',
            'platform-admin',
        ], true);
    }

    /**
     * Any admin-capable role (legacy admin included when company-scoped).
     */
    public static function isAdminRole(?string $role): bool
    {
        $normalized = strtolower(trim((string) $role));

        return in_array($normalized, [
            self::SuperAdmin->value,
            self::CompanyAdmin->value,
            self::LEGACY_ADMIN,
            'super-admin',
            'company-admin',
            'hr',
            'administrator',
            'platform_admin',
            'platform-admin',
            'tenant_admin',
            'tenant-admin',
        ], true);
    }

    public static function isEmployeeRole(?string $role): bool
    {
        return strtolower(trim((string) $role)) === self::Employee->value;
    }
}
