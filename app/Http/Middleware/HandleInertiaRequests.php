<?php

namespace App\Http\Middleware;

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
        $current = $user?->isAdmin() ? CurrentCompany::profile() : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'admin',
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],
            'companyName' => fn () => $current?->company_name
                ?: (CompanyProfile::query()->value('company_name') ?: 'Payslip Engine'),
            'currentCompany' => fn () => $current ? [
                'id' => $current->id,
                'company_name' => $current->company_name,
            ] : null,
            'companies' => fn () => ($user && $user->isAdmin())
                ? CompanyProfile::query()
                    ->where('is_active', true)
                    ->orderBy('company_name')
                    ->get(['id', 'company_name'])
                : [],
            'pendingStampedCount' => fn () => ($user && $user->isAdmin())
                ? StampedCopyRequest::where('status', 'pending')->count()
                : 0,
        ];
    }
}
