<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryIncrement;
use App\Models\SalaryLedgerEntry;
use App\Services\SalaryIncrementService;
use App\Support\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalaryIncrementController extends Controller
{
    public function __construct(private SalaryIncrementService $service) {}

    public function index(Request $request): Response
    {
        $companyId = CurrentCompany::id();
        $year = (int) ($request->integer('year') ?: now()->year);
        $employeeId = $request->integer('employee_id') ?: null;

        $employees = Employee::query()
            ->whereHas('branch', fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('full_name')
            ->get(['id', 'employee_code', 'full_name', 'base_salary', 'currency_code']);

        $selectedEmployee = $employeeId
            ? $employees->firstWhere('id', $employeeId)
            : null;

        $ledger = $selectedEmployee
            ? SalaryLedgerEntry::query()
                ->where('employee_id', $selectedEmployee->id)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->get()
            : collect();

        $increments = $selectedEmployee
            ? SalaryIncrement::query()
                ->where('employee_id', $selectedEmployee->id)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->get()
            : collect();

        return Inertia::render('SalaryIncrements/Index', [
            'employees' => $employees,
            'selected_employee' => $selectedEmployee,
            'ledger' => $ledger,
            'increments' => $increments,
            'yearly_report' => $this->service->yearlyReport($companyId, $year),
            'year' => $year,
            'success' => session('success'),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        // Used only via Inertia form; prefer client-side calc. Kept for completeness.
        $validated = $this->validateIncrement($request, false);
        $employee = $this->findCompanyEmployee((int) $validated['employee_id']);
        $preview = $this->service->preview($employee, $validated['increment_type'], (float) $validated['value']);

        return back()->with('preview', $preview);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateIncrement($request);
        $employee = $this->findCompanyEmployee((int) $validated['employee_id']);

        $this->service->apply($employee, $validated);

        return redirect()
            ->route('salary-increments.index', ['employee_id' => $employee->id])
            ->with('success', 'Salary increment applied successfully.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $companyId = CurrentCompany::id();
        $year = (int) ($request->integer('year') ?: now()->year);
        $rows = $this->service->yearlyReport($companyId, $year);

        $filename = "salary-increments-{$year}.csv";

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'employee_code',
                'full_name',
                'previous_basic_salary',
                'increment_type',
                'value',
                'new_basic_salary',
                'effective_date',
                'note',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['employee_code'],
                    $row['full_name'],
                    $row['previous_basic_salary'],
                    $row['increment_type'],
                    $row['value'],
                    $row['new_basic_salary'],
                    $row['effective_date'],
                    $row['note'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        $companyId = CurrentCompany::id();
        $year = (int) ($request->integer('year') ?: now()->year);
        $rows = $this->service->yearlyReport($companyId, $year);
        $company = CurrentCompany::profile();

        $pdf = Pdf::loadView('salary-increments.yearly-report', [
            'rows' => $rows,
            'year' => $year,
            'company_name' => $company?->company_name ?? 'Company',
        ]);

        return $pdf->download("salary-increments-{$year}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIncrement(Request $request, bool $requireDate = true): array
    {
        $companyId = CurrentCompany::id();

        $rules = [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(function ($q) use ($companyId) {
                    $q->whereIn('branch_id', function ($sub) use ($companyId) {
                        $sub->select('id')->from('branches')->where('company_id', $companyId);
                    });
                }),
            ],
            'increment_type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];

        if ($requireDate) {
            $rules['effective_date'] = ['required', 'date'];
        }

        return $request->validate($rules);
    }

    private function findCompanyEmployee(int $employeeId): Employee
    {
        $employee = Employee::with('branch')->findOrFail($employeeId);
        abort_unless(
            $employee->branch && (int) $employee->branch->company_id === (int) CurrentCompany::id(),
            404
        );

        return $employee;
    }
}
