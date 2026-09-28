<?php

namespace App\Http\Controllers;

use App\Services\EmployeeBulkImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiGuidelinesController extends Controller
{
    public function index(Request $request): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return Inertia::render('Api/Guidelines', [
            'baseUrl' => $baseUrl,
            'columns' => EmployeeBulkImportService::HEADERS,
            'endpoints' => [
                'template' => $baseUrl.'/api/v1/employees/bulk-import/template',
                'validate' => $baseUrl.'/api/v1/employees/bulk-import/validate',
                'commit' => $baseUrl.'/api/v1/employees/bulk-import',
                'payroll_sync' => $baseUrl.'/api/v1/payroll/sync',
            ],
            'uiImportUrl' => route('employees.index'),
            'templateDownloadUrl' => route('employees.bulk-import.template'),
            'tokens' => $request->user()
                ->tokens()
                ->latest()
                ->get(['id', 'name', 'last_used_at', 'created_at', 'expires_at'])
                ->map(fn ($token) => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => optional($token->last_used_at)?->toDateTimeString(),
                    'created_at' => optional($token->created_at)?->toDateTimeString(),
                    'expires_at' => optional($token->expires_at)?->toDateTimeString(),
                ]),
            'plainTextToken' => session('plainTextToken'),
            'success' => session('success'),
        ]);
    }

    public function createToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $token = $request->user()->createToken($validated['name'], ['employees:import']);

        return back()
            ->with('success', 'API token created. Copy it now — it will not be shown again.')
            ->with('plainTextToken', $token->plainTextToken);
    }

    public function revokeToken(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back()->with('success', 'API token revoked.');
    }
}
