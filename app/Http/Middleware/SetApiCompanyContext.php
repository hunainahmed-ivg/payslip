<?php

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        $companyId = $token?->company_id ?? $user?->effectiveCompanyId();

        if (! $companyId) {
            abort(403, 'This API token is not scoped to a company. Create a token from API Guidelines while your company is active.');
        }

        CurrentCompany::bind((int) $companyId);

        return $next($request);
    }
}
