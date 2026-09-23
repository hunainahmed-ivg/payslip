<?php

namespace App\Http\Controllers;

use App\Models\PayrollInput;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(): Response
    {
        $token = (string) config('services.virtuohr.token', '');

        return Inertia::render('Integrations/Index', [
            'webhookUrl' => url('/api/v1/payroll/sync'),
            'tokenConfigured' => $token !== '',
            'tokenMasked' => $token !== ''
                ? substr($token, 0, 6).str_repeat('•', 12)
                : 'Not configured (.env: VIRTUOHR_API_TOKEN)',
            'apiSyncCount' => PayrollInput::where('source', 'api')->count(),
            'csvSyncCount' => PayrollInput::where('source', 'csv')->count(),
            'recentApiSyncs' => PayrollInput::with('employee')
                ->where('source', 'api')
                ->latest('updated_at')
                ->limit(10)
                ->get(),
        ]);
    }
}