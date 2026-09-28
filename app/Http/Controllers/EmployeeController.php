<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $companyId = CurrentCompany::id();

        return Inertia::render('Employees/Index', [
            'employees' => Employee::with('branch')
                ->whereHas('branch', fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('full_name')
                ->get(),
            'branches' => Branch::where('company_id', $companyId)->orderBy('name')->get(),
            'success' => session('success'),
        ]);
    }

    public function show(Employee $employee): Response
    {
        $this->assertCompanyEmployee($employee);

        $employee->load('branch', 'salaryComponents');

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
            'masterComponents' => SalaryComponent::active()
                ->forCompany(CurrentCompany::id())
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'success' => session('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEmployee($request);

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->assertCompanyEmployee($employee);

        $validated = $this->validateEmployee($request, $employee->id);

        $employee->update($validated);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated successfully');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->assertCompanyEmployee($employee);

        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee deleted successfully');
    }

    private function validateEmployee(Request $request, ?int $ignoreId = null): array
    {
        $companyId = CurrentCompany::id();

        return $request->validate([
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_code')->ignore($ignoreId)],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($ignoreId)],
            'department' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'currency_code' => ['required', 'string', 'size:3'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'joined_on' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);
    }

    private function assertCompanyEmployee(Employee $employee): void
    {
        $employee->loadMissing('branch');
        abort_unless(
            $employee->branch && (int) $employee->branch->company_id === (int) CurrentCompany::id(),
            404
        );
    }
}
