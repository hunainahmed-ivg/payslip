<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
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

    public function isAdmin(): bool
    {
        return in_array(strtolower((string) $this->role), ['admin', 'hr', 'super-admin', 'administrator'], true);
    }

    public function isEmployee(): bool
    {
        return strtolower((string) $this->role) === 'employee';
    }

    /** Platform operator: admin not locked to a single tenant company. */
    public function isPlatformOperator(): bool
    {
        return $this->isAdmin() && $this->company_id === null;
    }

    public function usesCustomPermissions(): bool
    {
        return is_array($this->assigned_permissions);
    }

    public function effectiveCompanyId(): ?int
    {
        if ($this->company_id) {
            return (int) $this->company_id;
        }

        $branchCompanyId = $this->employee?->branch?->company_id;

        return $branchCompanyId ? (int) $branchCompanyId : null;
    }

    public function canAccessCompany(int $companyId): bool
    {
        if ($this->isPlatformOperator()) {
            return true;
        }

        return (int) $this->effectiveCompanyId() === (int) $companyId;
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = array_map('strtolower', (array) $roles);

        return in_array(strtolower((string) $this->role), $roles, true);
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
