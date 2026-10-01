<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\CompanyProfile;
use App\Models\StampedCopyRequest;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $current = ($user && ($user->hasPermission(Permission::CompaniesManage) || $user->effectiveCompanyId()))
            ? CurrentCompany::profile()
            : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->resolvedRole()?->value ?? ($user->role ?? 'employee'),
                    'role_label' => $user->roleLabel(),
                    'is_admin' => $user->isAdmin(),
                    'is_super_admin' => $user->isSuperAdmin(),
                    'is_company_admin' => $user->isCompanyAdmin(),
                    'is_platform_operator' => $user->isSuperAdmin(),
                    'company_id' => $user->company_id,
                    'employee_id' => $user->employee?->id,
                    'permissions' => $user->permissionNames(),
                ] : null,
            ],
            'companyName' => fn () => $current?->company_name
                ?: (CompanyProfile::query()->value('company_name') ?: 'Payslip Engine'),
            'currentCompany' => fn () => $current ? [
                'id' => $current->id,
                'company_name' => $current->company_name,
            ] : null,
            'companies' => fn () => ($user && ($user->isPlatformOperator() || $user->effectiveCompanyId()))
                ? CompanyProfile::query()
                    ->where('is_active', true)
                    ->when(! $user->isPlatformOperator(), fn ($q) => $q->where('id', $user->effectiveCompanyId()))
                    ->orderBy('company_name')
                    ->get(['id', 'company_name'])
                : [],
            'pendingStampedCount' => fn () => ($user && $user->hasPermission(Permission::StampedRequestsManage))
                ? StampedCopyRequest::where('status', 'pending')->count()
                : 0,
        ];
    }
}
