<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePayslipPdf;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Payslip;
use App\Services\PayrollCalculationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Payroll/Runs/Index', [
            'runs' => PayrollRun::withCount('items')->orderByDesc('period')->get(),
            'success' => session('success'),
        ]);
    }

    /**
     * "Run Monthly Payroll Draft" — computes every active employee.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', 'unique:payroll_runs,period'],
        ]);

        $run = PayrollRun::create([
            'period' => $validated['period'],
            'status' => 'draft',
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
        ]);

        $employees = Employee::where('is_active', true)->get();
        $engine = app(PayrollCalculationEngine::class);

        foreach ($employees as $employee) {
            $result = $engine->calculateForEmployee($employee, $validated['period']);

            $run->items()->create([
                'employee_id' => $employee->id,
                'base_salary' => $result['base_salary'],
                'currency_code' => $result['currency_code'],
                'earnings' => $result['earnings'],
                'deductions' => $result['deductions'],
                'gross_pay' => $result['gross_pay'],
                'total_deductions' => $result['total_deductions'],
                'net_pay' => $result['net_pay'],
            ]);
        }

        $this->refreshTotals($run);

        // 🔐 AUDIT LOG
        AuditLog::record('payroll_run.created', "Payroll {$validated['period']}", "Draft generated for {$employees->count()} employees.");

        return redirect()->route('payroll-runs.show', $run)
            ->with('success', "Draft payroll for {$validated['period']} generated for {$employees->count()} employees.");
    }

    public function show(PayrollRun $payrollRun): Response
    {
        $payrollRun->load(['items.employee.branch', 'generator']);

        return Inertia::render('Payroll/Runs/Show', [
            'run' => $payrollRun,
            'success' => session('success'),
        ]);
    }

    /**
     * HR line-item override + instant recalculation.
     */
    public function updateItem(Request $request, PayrollRun $payrollRun, PayrollRunItem $item): RedirectResponse
    {
        abort_unless($item->payroll_run_id === $payrollRun->id, 404);
        abort_if($payrollRun->status !== 'draft', 403, 'Only draft runs can be edited.');

        $validated = $request->validate([
            'overrides.unpaid_leave_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'overrides.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:744'],
        ]);

        $overrides = array_filter($validated['overrides'] ?? [], fn ($v) => $v !== null && $v !== '');

        $engine = app(PayrollCalculationEngine::class);
        $result = $engine->calculateForEmployee($item->employee, $payrollRun->period, $overrides);

        $item->update([
            'earnings' => $result['earnings'],
            'deductions' => $result['deductions'],
            'gross_pay' => $result['gross_pay'],
            'total_deductions' => $result['total_deductions'],
            'net_pay' => $result['net_pay'],
            'overrides' => $overrides !== [] ? $overrides : null,
        ]);

        $this->refreshTotals($payrollRun);

        return back()->with('success', "Overrides applied for {$item->employee->full_name} — net pay recalculated.");
    }

    /**
     * Approve & freeze the snapshot.
     */
    public function approve(PayrollRun $payrollRun): RedirectResponse
    {
        abort_if($payrollRun->status !== 'draft', 403, 'This run is already approved.');

        $this->refreshTotals($payrollRun);

        $payrollRun->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // 🔐 AUDIT LOG
        AuditLog::record('payroll_run.approved', "Payroll {$payrollRun->period}", 'Snapshot frozen & locked.');

        return back()->with('success', "Payroll {$payrollRun->period} approved & frozen. Snapshot locked.");
    }

    public function destroy(PayrollRun $payrollRun): RedirectResponse
    {
        abort_if($payrollRun->status !== 'draft', 403, 'Approved runs cannot be deleted.');

        $period = $payrollRun->period;
        $payrollRun->delete();

        return redirect()->route('payroll-runs.index')->with('success', "Draft run {$period} deleted.");
    }

    private function refreshTotals(PayrollRun $run): void
    {
        $run->update([
            'total_earnings' => $run->items()->sum('gross_pay'),
            'total_deductions' => $run->items()->sum('total_deductions'),
            'total_net_pay' => $run->items()->sum('net_pay'),
        ]);
    }

    /**
     * Batch auto-publish: freeze snapshots into payslips + queue PDF generation.
     */
    public function publish(PayrollRun $payrollRun): RedirectResponse
    {
        abort_if($payrollRun->status !== 'approved', 403, 'Only approved runs can be published.');

        $payrollRun->load('items.employee.branch');

        foreach ($payrollRun->items as $item) {
            $snapshot = [
                'period' => $payrollRun->period,
                'employee' => [
                    'name' => $item->employee->full_name,
                    'code' => $item->employee->employee_code,
                    'department' => $item->employee->department,
                    'designation' => $item->employee->designation,
                    'branch' => $item->employee->branch?->name,
                ],
                'base_salary' => $item->base_salary,
                'currency_code' => $item->currency_code,
                'earnings' => $item->earnings,
                'deductions' => $item->deductions,
                'gross_pay' => $item->gross_pay,
                'total_deductions' => $item->total_deductions,
                'net_pay' => $item->net_pay,
            ];

            $payslip = Payslip::updateOrCreate(
                ['employee_id' => $item->employee_id, 'period' => $payrollRun->period],
                [
                    'payroll_run_id' => $payrollRun->id,
                    'snapshot' => $snapshot,
                    'status' => 'queued',
                ],
            );

            GeneratePayslipPdf::dispatch($payslip);
        }

        // 🔐 AUDIT LOG
        AuditLog::record('payroll_run.published', "Payroll {$payrollRun->period}", count($payrollRun->items).' payslips queued for PDF generation.');

        return back()->with('success', count($payrollRun->items).' payslips queued for PDF generation & auto-publishing.');
    }
}