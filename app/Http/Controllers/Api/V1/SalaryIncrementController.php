<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalaryLedgerEntry;
use App\Services\SalaryIncrementService;
use App\Support\ApiResponse;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalaryIncrementController extends Controller
{
    public function __construct(private SalaryIncrementService $service) {}

    public function searchEmployees(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $companyId = CurrentCompany::id();

        $employees = Employee::query()
            ->whereHas('branch', fn ($query) => $query->where('company_id', $companyId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('full_name', 'like', "%{$q}%")
                        ->orWhere('employee_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('full_name')
            ->limit(25)
            ->get(['id', 'employee_code', 'full_name', 'base_salary', 'currency_code']);

        return ApiResponse::ok(['employees' => $employees]);
    }

    public function preview(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateIncrement($request, false);
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Validation failed.', 422, [
                'errors' => $e->errors(),
            ]);
        }

        $employee = $this->findCompanyEmployee((int) $validated['employee_id']);
        if (! $employee) {
            return ApiResponse::error('Employee not found.', 404);
        }

        try {
            $preview = $this->service->preview($employee, $validated['increment_type'], (float) $validated['value']);
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Validation failed.', 422, [
                'errors' => $e->errors(),
            ]);
        }

        return ApiResponse::ok($preview);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateIncrement($request);
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Validation failed.', 422, [
                'errors' => $e->errors(),
            ]);
        }

        $employee = $this->findCompanyEmployee((int) $validated['employee_id']);
        if (! $employee) {
            return ApiResponse::error('Employee not found.', 404);
        }

        try {
            $increment = $this->service->apply($employee, $validated);
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Validation failed.', 422, [
                'errors' => $e->errors(),
            ]);
        }

        return ApiResponse::ok([
            'salary_increment' => $increment,
            'employee' => $employee->fresh(['branch']),
        ], 201);
    }

    public function ledger(Employee $employee): JsonResponse
    {
        if (! $this->belongsToCurrentCompany($employee)) {
            return ApiResponse::error('Employee not found.', 404);
        }

        $entries = SalaryLedgerEntry::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::ok([
            'employee_id' => $employee->id,
            'current_basic_salary' => $employee->base_salary,
            'ledger' => $entries,
        ]);
    }

    public function yearlyReport(Request $request): JsonResponse
    {
        $year = (int) ($request->integer('year') ?: now()->year);
        $rows = $this->service->yearlyReport(CurrentCompany::id(), $year);

        return ApiResponse::ok([
            'year' => $year,
            'increments' => $rows,
        ]);
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

    private function findCompanyEmployee(int $employeeId): ?Employee
    {
        $employee = Employee::with('branch')->find($employeeId);

        if (! $employee || ! $this->belongsToCurrentCompany($employee)) {
            return null;
        }

        return $employee;
    }

    private function belongsToCurrentCompany(Employee $employee): bool
    {
        $employee->loadMissing('branch');

        return $employee->branch
            && (int) $employee->branch->company_id === (int) CurrentCompany::id();
    }
}
