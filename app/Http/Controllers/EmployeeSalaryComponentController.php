<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeSalaryComponentController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $this->validateOverride($request, $employee);

        if(!empty($validated['salary_component_id'])){
            $master = SalaryComponent::findOrFail($validated['salary_component_id']);
            $validated['title'] = $master->name;
            $validated['type'] = $master->type;
        }

        $employee->salaryComponents()->create($validated);

        return back()->with('success', 'Salary component added successfully');
    }

    public function update(Request $request, Employee $employee, EmployeeSalaryComponent $override): RedirectResponse
    {
        abort_unless($override->employee_id === $employee->id, 404);

        $validated = $this->validateOverride($request, $employee, $override->id);

        if (! empty($validated['salary_component_id'])) {
            $master = SalaryComponent::findOrFail($validated['salary_component_id']);
            $validated['title'] = $master->name;
            $validated['type'] = $master->type;
        }

        $override->update($validated);

        return back()->with('success', 'Salary mapping updated.');
    }

    /**
     * Remove override → component reverts to global default.
     */
    public function destroy(Employee $employee, EmployeeSalaryComponent $override): RedirectResponse
    {
        abort_unless($override->employee_id === $employee->id, 404);

        $override->delete();

        return back()->with('success', 'Override removed — component reverted to global default.');
    }

    private function validateOverride(Request $request, Employee $employee, ?int $ignoreId = null): array
    {
        return $request->validate([
            'salary_component_id' => [
                'nullable', 'integer', 'exists:salary_components,id',
                Rule::unique('employee_salary_components', 'salary_component_id')
                    ->where('employee_id', $employee->id)
                    ->ignore($ignoreId),
            ],
            'title' => ['required_without:salary_component_id', 'nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'calculation_type' => ['required', Rule::in(['fixed', 'percentage', 'statutory'])],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);
    }
}