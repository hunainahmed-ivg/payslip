<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
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
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        $users = User::query()
            ->with('company:id,company_name')
            ->when(! $actor->isPlatformOperator(), function ($query) use ($actor) {
                $companyId = $actor->effectiveCompanyId();
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
                'role' => $user->role,
                'role_label' => $user->roleLabel(),
                'company_id' => $user->company_id,
                'company_name' => $user->company?->company_name,
                'uses_custom_permissions' => $user->usesCustomPermissions(),
                'permissions' => $user->permissionNames(),
            ]);

        $companies = $actor->isPlatformOperator()
            ? CompanyProfile::query()->where('is_active', true)->orderBy('company_name')->get(['id', 'company_name'])
            : CompanyProfile::query()
                ->where('id', $actor->effectiveCompanyId())
                ->get(['id', 'company_name']);

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'companies' => $companies,
            'permissionCatalog' => PermissionCatalog::grouped(),
            'roles' => [
                ['value' => 'admin', 'label' => 'Administrator'],
                ['value' => 'employee', 'label' => 'Employee'],
            ],
            'canAssignCompany' => $actor->isPlatformOperator(),
            'defaultCompanyId' => CurrentCompany::id(),
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
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
        abort_unless($this->canManageUser($actor, $user), 403);

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
        abort_unless($this->canManageUser($actor, $user), 403);

        if ((int) $actor->id === (int) $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return back()->with('success', 'User removed.');
    }

    private function canManageUser(User $actor, User $target): bool
    {
        if ($actor->isPlatformOperator()) {
            return true;
        }

        $companyId = $actor->effectiveCompanyId();

        return $companyId !== null && (int) $target->company_id === (int) $companyId;
    }

    /** @return array<string, mixed> */
    private function validateUserPayload(Request $request, User $actor, ?User $existing = null): array
    {
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
            'role' => ['required', Rule::in(['admin', 'employee'])],
            'company_id' => ['nullable', 'integer', 'exists:company_profiles,id'],
            'use_custom_permissions' => ['required', 'boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in($allowedPermissions)],
        ]);

        if ($actor->isPlatformOperator()) {
            if (($validated['role'] ?? '') === 'admin' && empty($validated['company_id'])) {
                // platform-wide admin — allowed
            } elseif (($validated['role'] ?? '') === 'admin' && ! empty($validated['company_id'])) {
                // tenant admin — allowed
            } elseif (($validated['role'] ?? '') === 'employee') {
                $validated['company_id'] = $validated['company_id']
                    ?? $actor->effectiveCompanyId()
                    ?? CurrentCompany::id();
            }
        } else {
            $validated['company_id'] = $actor->effectiveCompanyId();
            abort_unless($validated['company_id'], 422, 'Your account is not linked to a company.');
        }

        if (! $validated['use_custom_permissions']) {
            $validated['permissions'] = RolePermissions::defaultsForRole(
                $validated['role'],
                $validated['company_id'] ?? null,
            );
        }

        if ($validated['use_custom_permissions'] && count($validated['permissions'] ?? []) === 0) {
            abort(422, 'Select at least one permission when using custom access.');
        }

        if (! empty($validated['company_id']) && ! $actor->canAccessCompany((int) $validated['company_id'])) {
            abort(403, 'You cannot assign users to that company.');
        }

        if (! $actor->isPlatformOperator() && in_array(Permission::CompaniesManage->value, $validated['permissions'] ?? [], true)) {
            abort(422, 'Only platform operators can grant company management access.');
        }

        return $validated;
    }
}
