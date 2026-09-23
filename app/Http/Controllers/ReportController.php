<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Payslip;
use App\Models\StampedCopyRequest;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        $latestRun = PayrollRun::where('status', '!=', 'draft')
            ->orderByDesc('period')
            ->first();

        $currencyBreakdown = [];

        if ($latestRun) {
            $currencyBreakdown = PayrollRunItem::where('payroll_run_id', $latestRun->id)
                ->get()
                ->groupBy('currency_code')
                ->map(fn ($items, $code) => [
                    'currency_code' => $code,
                    'employees' => $items->count(),
                    'gross_pay' => round((float) $items->sum('gross_pay'), 2),
                    'total_deductions' => round((float) $items->sum('total_deductions'), 2),
                    'net_pay' => round((float) $items->sum('net_pay'), 2),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Reports/Index', [
            'stats' => [
                'active_employees' => Employee::where('is_active', true)->count(),
                'branches' => Branch::count(),
                'payroll_runs' => PayrollRun::count(),
                'published_payslips' => Payslip::where('status', 'published')->count(),
                'pending_requests' => StampedCopyRequest::where('status', 'pending')->count(),
            ],
            'runs' => PayrollRun::withCount('items')->orderByDesc('period')->get(),
            'latestRun' => $latestRun,
            'currencyBreakdown' => $currencyBreakdown,
        ]);
    }
}