<?php

namespace App\Http\Controllers;

use App\Models\PayrollInput;
use App\Models\PayrollSyncEvent;
use App\Support\CurrentCompany;
use App\Services\VirtuoHRWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(): Response
    {
        $company = CurrentCompany::profile();
        $companyId = CurrentCompany::id();

        $sigHeader = (string) config('services.virtuohr.webhook_signature_header', 'X-VirtuoHR-Signature');
        $tsHeader = (string) config('services.virtuohr.webhook_timestamp_header', 'X-VirtuoHR-Timestamp');
        $idemHeader = (string) config('services.virtuohr.webhook_idempotency_header', 'Idempotency-Key');

        $companySecret = $company?->webhook_secret;
        $legacySecret = (string) config('services.virtuohr.webhook_secret', '');
        $secretConfigured = ($companySecret !== null && $companySecret !== '') || $legacySecret !== '';
        $secretMasked = $companySecret
            ? substr($companySecret, 0, 4).str_repeat('•', 12)
            : ($legacySecret !== ''
                ? substr($legacySecret, 0, 4).str_repeat('•', 12).' (global .env fallback)'
                : 'Not configured — generate a company webhook secret below.');

        $employeeIds = $companyId
            ? \App\Models\Employee::query()
                ->whereHas('branch', fn ($q) => $q->where('company_id', $companyId))
                ->pluck('id')
            : collect();

        return Inertia::render('Integrations/Index', [
            'webhookUrl' => url('/api/v1/payroll/sync'),
            'companyId' => $companyId,
            'companyName' => $company?->company_name,
            'companyHeader' => VirtuoHRWebhookVerifier::COMPANY_HEADER,
            'secretConfigured' => $secretConfigured,
            'secretMasked' => $secretMasked,
            'hasCompanySecret' => $companySecret !== null && $companySecret !== '',
            'signatureHeader' => $sigHeader,
            'timestampHeader' => $tsHeader,
            'idempotencyHeader' => $idemHeader,
            'apiSyncCount' => $employeeIds->isEmpty()
                ? 0
                : PayrollInput::where('source', 'api')->whereIn('employee_id', $employeeIds)->count(),
            'csvSyncCount' => $employeeIds->isEmpty()
                ? 0
                : PayrollInput::where('source', 'csv')->whereIn('employee_id', $employeeIds)->count(),
            'recentApiSyncs' => $employeeIds->isEmpty()
                ? []
                : PayrollInput::with('employee')
                    ->where('source', 'api')
                    ->whereIn('employee_id', $employeeIds)
                    ->latest('updated_at')
                    ->limit(10)
                    ->get(),
            'recentEvents' => PayrollSyncEvent::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->latest()
                ->limit(10)
                ->get(),
            'success' => session('success'),
            'plainWebhookSecret' => session('plainWebhookSecret'),
        ]);
    }

    public function regenerateWebhookSecret(Request $request): RedirectResponse
    {
        $company = CurrentCompany::profile();
        abort_unless($company, 422, 'Select an active company first.');

        $plain = Str::random(48);
        $company->webhook_secret = $plain;
        $company->save();

        return back()
            ->with('success', 'Webhook secret rotated. Update your HR system with the new value.')
            ->with('plainWebhookSecret', $plain);
    }

    public function syncStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $period = $validated['period'];
        $companyId = CurrentCompany::id();

        $employeeIds = $companyId
            ? \App\Models\Employee::query()
                ->whereHas('branch', fn ($q) => $q->where('company_id', $companyId))
                ->pluck('id')
            : collect();

        $inputs = $employeeIds->isEmpty()
            ? 0
            : PayrollInput::where('period', $period)->where('source', 'api')->whereIn('employee_id', $employeeIds)->count();

        $lastEvent = PayrollSyncEvent::query()
            ->where('period', $period)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->first();

        return response()->json([
            'period' => $period,
            'api_records' => $inputs,
            'last_event' => $lastEvent,
        ]);
    }
}
