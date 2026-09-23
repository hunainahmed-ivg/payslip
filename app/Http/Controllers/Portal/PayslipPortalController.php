<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\StampedCopyRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayslipPortalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->employee) {
            return Inertia::render('Portal/Payslips', [
                'employee' => null,
                'payslips' => [],
                'requests' => [],
                'success' => session('success'),
                'error' => session('error'),
            ]);
        }

        $payslips = Payslip::where('employee_id', $user->employee->id)
            ->where('status', 'published')
            ->orderByDesc('period')
            ->get()
            ->map(function ($payslip) {
                return [
                    'id' => $payslip->id,
                    'period' => $payslip->period,
                    'currency_code' => $payslip->snapshot['currency_code'] ?? 'USD',
                    'gross_pay' => $payslip->snapshot['gross_pay'] ?? 0,
                    'total_deductions' => $payslip->snapshot['total_deductions'] ?? 0,
                    'net_pay' => $payslip->snapshot['net_pay'] ?? 0,
                    'published_at' => $payslip->published_at?->toDateTimeString(),
                    'pdf_url' => $payslip->pdf_url,
                ];
            });

        $requests = StampedCopyRequest::with('payslip')
            ->where('employee_id', $user->employee->id)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Portal/Payslips', [
            'employee' => $user->employee->only(['full_name', 'employee_code', 'department']),
            'payslips' => $payslips,
            'requests' => $requests,
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }
}