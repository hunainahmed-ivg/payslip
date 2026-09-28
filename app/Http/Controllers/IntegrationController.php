<?php

namespace App\Http\Controllers;

use App\Models\PayrollInput;
use App\Models\PayrollSyncEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(): Response
    {
        $secret = (string) config('services.virtuohr.webhook_secret', '');
        $sigHeader = (string) config('services.virtuohr.webhook_signature_header', 'X-VirtuoHR-Signature');
        $tsHeader = (string) config('services.virtuohr.webhook_timestamp_header', 'X-VirtuoHR-Timestamp');
        $idemHeader = (string) config('services.virtuohr.webhook_idempotency_header', 'Idempotency-Key');

        return Inertia::render('Integrations/Index', [
            'webhookUrl' => url('/api/v1/payroll/sync'),
            'secretConfigured' => $secret !== '',
            'secretMasked' => $secret !== ''
                ? substr($secret, 0, 4).str_repeat('•', 12)
                : 'Not configured (.env: VIRTUOHR_WEBHOOK_SECRET)',
            'signatureHeader' => $sigHeader,
            'timestampHeader' => $tsHeader,
            'idempotencyHeader' => $idemHeader,
            'apiSyncCount' => PayrollInput::where('source', 'api')->count(),
            'csvSyncCount' => PayrollInput::where('source', 'csv')->count(),
            'recentApiSyncs' => PayrollInput::with('employee')
                ->where('source', 'api')
                ->latest('updated_at')
                ->limit(10)
                ->get(),
            'recentEvents' => PayrollSyncEvent::query()
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function syncStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $period = $validated['period'];

        $inputs = PayrollInput::where('period', $period)->where('source', 'api')->count();
        $lastEvent = PayrollSyncEvent::where('period', $period)->latest()->first();

        return response()->json([
            'period' => $period,
            'api_records' => $inputs,
            'last_event' => $lastEvent,
        ]);
    }
}
