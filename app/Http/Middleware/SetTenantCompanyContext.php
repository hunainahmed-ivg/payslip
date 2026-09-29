<?php

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $companyId = $user->effectiveCompanyId();

            if ($companyId !== null && ! $user->isPlatformOperator()) {
                CurrentCompany::bind($companyId);
            }
        }

        return $next($request);
    }
}
