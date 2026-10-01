<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\PermissionCatalog;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $this->assertAdminActor($actor);

        $users = User::query()
            ->with('company:id,company_name')
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor) {
                $companyId = $actor->effectiveCompanyId();
                abort_unless($companyId !== null, 403, 'Your account is not linked to a company.');

                // Company admins see users belonging to their company only.
                $query->where(function ($inner) use ($companyId) {
                    $inner->where('company_id', $companyId)
                        ->orWhereHas('employee.branch', fn ($b) => $b->where('company_id', $companyId));
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->resolvedRole()?->value ?? $user->role,
                'role_label' => $user->roleLabel(),
                'company_id' => $user->company_id,
                'company_name' => $user->company?->company_name,
                'uses_custom_permissions' => $user->usesCustomPermissions(),
                'permissions' => $user->permissionNames(),
                'can_manage' => $this->canManageUser($actor, $user),
            ]);

        $companies = $actor->isSuperAdmin()
            ? CompanyProfile::query()->where('is_active', true)->orderBy('company_name')->get(['id', 'company_name'])
            : CompanyProfile::query()
                ->where('id', $actor->effectiveCompanyId())
                ->get(['id', 'company_name']);

        $permissionCatalog = PermissionCatalog::grouped();
        if (! $actor->isSuperAdmin()) {
            $permissionCatalog = array_values(array_filter(
                $permissionCatalog,
                fn (array $item) => $item['value'] !== Permission::CompaniesManage->value,
            ));
        }

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'companies' => $companies,
            'permissionCatalog' => $permissionCatalog,
            'roles' => RolePermissions::assignableRolesFor($actor),
            'canAssignCompany' => $actor->isSuperAdmin(),
            'defaultCompanyId' => $actor->isSuperAdmin()
                ? CurrentCompany::id()
                : $actor->effectiveCompanyId(),
            'actorRole' => $actor->resolvedRole()?->value,
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertAdminActor($actor);

        $validated = $this->validateUserPayload($request, $actor);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'company_id' => $validated['company_id'] ?? null,
            'assigned_permissions' => $validated['use_custom_permissions']
                ? $validated['permissions']
                : null,
        ]);

        return back()->with('success', 'User account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->assertAdminActor($actor);
        abort_unless($this->canManageUser($actor, $user), 403, "You don't have permission to manage this user.");

        $validated = $this->validateUserPayload($request, $actor, $user);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->company_id = $validated['company_id'] ?? null;

        if ($validated['password'] ?? null) {
            $user->password = Hash::make($validated['password']);
        }

        $user->assigned_permissions = $validated['use_custom_permissions']
            ? $validated['permissions']
            : null;

        $user->save();

        return back()->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->assertAdminActor($actor);
        abort_unless($this->canManageUser($actor, $user), 403, "You don't have permission to manage this user.");

        if ((int) $actor->id === (int) $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return back()->with('success', 'User removed.');
    }

    private function assertAdminActor(?User $actor): void
    {
        abort_unless(
            $actor !== null && $actor->isAdmin(),
            403,
            'Only Company Admins and Super Admins can manage user access.'
        );
    }

    private function canManageUser(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if (! $actor->isCompanyAdmin()) {
            return false;
        }

        $companyId = $actor->effectiveCompanyId();
        if ($companyId === null) {
            return false;
        }

        $targetCompanyId = $target->company_id
            ?? $target->employee?->branch?->company_id;

        if ($targetCompanyId === null || (int) $targetCompanyId !== (int) $companyId) {
            return false;
        }

        // Company admins manage employees in their company — not peer admins or super admins.
        return $target->isEmployee();
    }

    /** @return array<string, mixed> */
    private function validateUserPayload(Request $request, User $actor, ?User $existing = null): array
    {
        $allowedRoles = array_column(RolePermissions::assignableRolesFor($actor), 'value');
        $allowedPermissions = Permission::values();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($existing?->id),
            ],
            'password' => [$existing ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in($allowedRoles)],
            'company_id' => ['nullable', 'integer', 'exists:company_profiles,id'],
            'use_custom_permissions' => ['required', 'boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in($allowedPermissions)],
        ]);

        $role = $validated['role'];

        if ($actor->isSuperAdmin()) {
            if ($role === UserRole::SuperAdmin->value) {
                $validated['company_id'] = null;
            } elseif ($role === UserRole::CompanyAdmin->value) {
                if (empty($validated['company_id'])) {
                    throw ValidationException::withMessages([
                        'company_id' => 'Select a company for this Company Admin.',
                    ]);
                }
            } elseif ($role === UserRole::Employee->value) {
                $validated['company_id'] = $validated['company_id']
                    ?? CurrentCompany::id();

                if (empty($validated['company_id'])) {
                    throw ValidationException::withMessages([
                        'company_id' => 'Select a company for this employee.',
                    ]);
                }
            }
        } else {
            // Company Admin: lock to their company and employee role only.
            $validated['company_id'] = $actor->effectiveCompanyId();
            $validated['role'] = UserRole::Employee->value;

            if (! $validated['company_id']) {
                throw ValidationException::withMessages([
                    'company_id' => 'Your account is not linked to a company.',
                ]);
            }
        }

        if (! empty($validated['company_id']) && ! $actor->canAccessCompany((int) $validated['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => "You don't have access to this company.",
            ]);
        }

        if (! $validated['use_custom_permissions']) {
            $validated['permissions'] = RolePermissions::defaultsForRole(
                $validated['role'],
                $validated['company_id'] ?? null,
            );
        }

        // Reject elevating employees with hard-admin pages; optional grants (e.g. Visual Identity) are allowed.
        if ($validated['role'] === UserRole::Employee->value && $validated['use_custom_permissions']) {
            $allowed = RolePermissions::assignableToEmployee();
            $extra = array_values(array_diff($validated['permissions'] ?? [], $allowed));
            if ($extra !== []) {
                throw ValidationException::withMessages([
                    'permissions' => 'Employees cannot be given admin pages such as Users & Access or Employees.',
                ]);
            }
        }

        $validated['permissions'] = RolePermissions::sanitizeForRole(
            $validated['role'],
            $validated['permissions'] ?? [],
            $validated['company_id'] ?? null,
        );

        if ($validated['use_custom_permissions'] && count($validated['permissions']) === 0) {
            throw ValidationException::withMessages([
                'permissions' => 'Select at least one page this user can open.',
            ]);
        }

        return $validated;
    }
}
