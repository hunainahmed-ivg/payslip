<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePayslipPdf;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Payslip;
use App\Services\PayrollCalculationEngine;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunController extends Controller
{
    public function index(): Response
    {
        $companyId = CurrentCompany::id();

        return Inertia::render('Payroll/Runs/Index', [
            'runs' => PayrollRun::withCount('items')
                ->where('company_id', $companyId)
                ->orderByDesc('period')
                ->get(),
            'success' => session('success'),
        ]);
    }

    /**
     * Generate draft payroll for a period.
     */
    public function store(Request $request): RedirectResponse
    {
        $companyId = CurrentCompany::id();

        $validated = $request->validate([
            'period' => [
                'required',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                Rule::unique('payroll_runs', 'period')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $run = PayrollRun::create([
            'company_id' => $companyId,
            'period' => $validated['period'],
            'status' => 'draft',
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
        ]);

        $employees = Employee::where('is_active', true)
            ->whereHas('branch', fn ($q) => $q->where('company_id', $companyId))
            ->get();
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

        AuditLog::record('payroll_run.created', "Payroll {$validated['period']}", "Draft generated for {$employees->count()} employees.");

        return redirect()->route('payroll-runs.show', $run)
            ->with('success', "Draft payroll for {$validated['period']} generated for {$employees->count()} employees.");
    }

    public function show(PayrollRun $payrollRun): Response
    {
        $this->assertCompanyRun($payrollRun);

        $payrollRun->load(['items.employee.branch', 'generator']);

        $payslips = Payslip::where('payroll_run_id', $payrollRun->id)
            ->get()
            ->keyBy('employee_id');

        return Inertia::render('Payroll/Runs/Show', [
            'run' => $payrollRun,
            'payslips' => $payslips,
            'success' => session('success'),
        ]);
    }

    /**
     * HR line-item override + instant recalculation.
     */
    public function updateItem(Request $request, PayrollRun $payrollRun, PayrollRunItem $item): RedirectResponse
    {
        $this->assertCompanyRun($payrollRun);
        abort_unless($item->payroll_run_id === $payrollRun->id, 404);
        abort_if($payrollRun->status !== 'draft', 403, 'Only draft runs can be edited.');

        $validated = $request->validate([
            'overrides.unpaid_leave_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'overrides.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:744'],
            'overrides.bonus_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $overrides = array_filter(
            $validated['overrides'] ?? [],
            fn ($v) => $v !== null && $v !== ''
        );

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
     * Approve & freeze immutable payslip snapshots.
     */
    public function approve(PayrollRun $payrollRun): RedirectResponse
    {
        $this->assertCompanyRun($payrollRun);
        abort_if($payrollRun->status !== 'draft', 403, 'This run is already approved.');

        $this->refreshTotals($payrollRun);
        $payrollRun->load('items.employee.branch');

        foreach ($payrollRun->items as $item) {
            Payslip::updateOrCreate(
                ['employee_id' => $item->employee_id, 'period' => $payrollRun->period],
                [
                    'payroll_run_id' => $payrollRun->id,
                    'snapshot' => $this->buildSnapshot($payrollRun, $item),
                    'status' => 'approved',
                    'published_at' => null,
                    'pdf_path' => null,
                ],
            );
        }

        $payrollRun->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        AuditLog::record('payroll_run.approved', "Payroll {$payrollRun->period}", 'Snapshot frozen & locked.');

        return back()->with('success', "Payroll {$payrollRun->period} approved & frozen. Snapshots locked.");
    }

    public function destroy(PayrollRun $payrollRun): RedirectResponse
    {
        $this->assertCompanyRun($payrollRun);
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
     * Queue PDF generation & email distribution from frozen snapshots.
     */
    public function publish(PayrollRun $payrollRun): RedirectResponse
    {
        $this->assertCompanyRun($payrollRun);
        abort_if($payrollRun->status !== 'approved', 403, 'Only approved runs can be published.');

        $payrollRun->load('items.employee.branch');

        foreach ($payrollRun->items as $item) {
            $payslip = Payslip::updateOrCreate(
                ['employee_id' => $item->employee_id, 'period' => $payrollRun->period],
                [
                    'payroll_run_id' => $payrollRun->id,
                    'snapshot' => $this->buildSnapshot($payrollRun, $item),
                    'status' => 'queued',
                ],
            );

            GeneratePayslipPdf::dispatch($payslip);
        }

        AuditLog::record('payroll_run.published', "Payroll {$payrollRun->period}", count($payrollRun->items).' payslips queued for PDF generation.');

        return back()->with('success', count($payrollRun->items).' payslips queued for PDF generation & publishing.');
    }

    private function buildSnapshot(PayrollRun $payrollRun, PayrollRunItem $item): array
    {
        return [
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
            'overrides' => $item->overrides,
        ];
    }

    private function assertCompanyRun(PayrollRun $payrollRun): void
    {
        abort_unless((int) $payrollRun->company_id === (int) CurrentCompany::id(), 404);
    }
}
