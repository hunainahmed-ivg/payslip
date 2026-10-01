<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\CompanyProfile;
use App\Support\RolePermissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'company_id', 'assigned_permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'assigned_permissions' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function resolvedRole(): ?UserRole
    {
        $role = strtolower(trim((string) $this->role));

        if (UserRole::isSuperAdminRole($role) || ($role === UserRole::LEGACY_ADMIN && $this->company_id === null)) {
            return UserRole::SuperAdmin;
        }

        if (UserRole::isAdminRole($role)) {
            return UserRole::CompanyAdmin;
        }

        if (UserRole::isEmployeeRole($role)) {
            return UserRole::Employee;
        }

        return UserRole::tryFromFlexible($role);
    }

    public function isSuperAdmin(): bool
    {
        return $this->resolvedRole() === UserRole::SuperAdmin;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->resolvedRole() === UserRole::CompanyAdmin;
    }

    /** Any admin (super or company). Prefer isSuperAdmin()/isCompanyAdmin() for new code. */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isCompanyAdmin();
    }

    public function isEmployee(): bool
    {
        return $this->resolvedRole() === UserRole::Employee;
    }

    /** Alias kept for existing call sites — Super Admin is not tied to one company. */
    public function isPlatformOperator(): bool
    {
        return $this->isSuperAdmin();
    }

    public function usesCustomPermissions(): bool
    {
        return is_array($this->assigned_permissions);
    }

    public function effectiveCompanyId(): ?int
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        if ($this->company_id) {
            return (int) $this->company_id;
        }

        $branchCompanyId = $this->employee?->branch?->company_id;

        return $branchCompanyId ? (int) $branchCompanyId : null;
    }

    public function canAccessCompany(int $companyId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $ownCompanyId = $this->effectiveCompanyId();

        return $ownCompanyId !== null && (int) $ownCompanyId === (int) $companyId;
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = array_map('strtolower', (array) $roles);
        $current = strtolower((string) ($this->resolvedRole()?->value ?? $this->role));

        return in_array($current, $roles, true)
            || in_array(strtolower((string) $this->role), $roles, true);
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        return RolePermissions::forUser($this);
    }

    public function hasPermission(string|Permission $permission): bool
    {
        $key = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($key, $this->permissionNames(), true);
    }

    public function roleLabel(): string
    {
        return RolePermissions::roleLabel($this);
    }
}
