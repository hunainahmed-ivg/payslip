<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollInput;
use App\Models\SalaryComponent;

class PayrollCalculationEngine
{
    /**
     * Compute the full salary breakdown for one employee for a period.
     *
     * Priority: HR manual overrides > employee contract overrides > master global rules.
     */
    public function calculateForEmployee(Employee $employee, string $period, array $overrides = []): array
    {
        $baseSalary = (float) $employee->base_salary;

        // Attendance / leave inputs ingested in Phase 4 (CSV or VirtuoHR API)
        $input = PayrollInput::where('employee_id', $employee->id)
            ->where('period', $period)
            ->first();

        $totalWorkingDays = (float) ($input?->total_working_days ?? 22);
        $unpaidLeaveDays = (float) ($input?->unpaid_leave_days ?? 0);
        $overtimeHours = (float) ($input?->overtime_hours ?? 0);

        // HR manual overrides from the review grid win over ingested data
        $unpaidLeaveDays = (float) ($overrides['unpaid_leave_days'] ?? $unpaidLeaveDays);
        $overtimeHours = (float) ($overrides['overtime_hours'] ?? $overtimeHours);

        // Employee-level overrides & custom items (Phase 3)
        $employeeComponents = $employee->salaryComponents()->get()->keyBy(
            fn ($ec) => $ec->salary_component_id ?? 'custom-'.$ec->id,
        );

        $earnings = [];
        $deductions = [];

        // --- 1) Basic Salary line (from employee contract) ---
        $earnings[] = [
            'title' => 'Basic Salary',
            'slug' => 'basic_salary',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'value' => $baseSalary,
            'amount' => round($baseSalary, 2),
        ];

        // --- 2) Master components (global default rules) ---
        $masterComponents = SalaryComponent::active()
            ->where('slug', '!=', 'basic_salary')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        foreach ($masterComponents as $master) {
            $override = $employeeComponents->get($master->id);

            $calcType = $override?->calculation_type ?? $master->calculation_type;
            $value = (float) ($override?->value ?? $master->default_value);

            // Statutory formula: (Basic / Total Working Days) × Unpaid Leave Days
            if ($master->slug === 'unpaid_leave') {
                $amount = $totalWorkingDays > 0
                    ? round(($baseSalary / $totalWorkingDays) * $unpaidLeaveDays, 2)
                    : 0.0;

                if ($amount <= 0) {
                    continue; // no unpaid leave this month → skip the line
                }

                $deductions[] = [
                    'title' => $master->name,
                    'slug' => $master->slug,
                    'type' => 'deduction',
                    'calculation_type' => 'statutory',
                    'value' => $unpaidLeaveDays,
                    'amount' => $amount,
                ];

                continue;
            }

            // Statutory tax: uses employee override value when present
            if ($master->slug === 'income_tax') {
                $amount = $override ? round((float) $override->value, 2) : 0.0;

                if ($amount <= 0) {
                    continue;
                }

                $deductions[] = [
                    'title' => $master->name,
                    'slug' => $master->slug,
                    'type' => 'deduction',
                    'calculation_type' => 'statutory',
                    'value' => $amount,
                    'amount' => $amount,
                ];

                continue;
            }

            // Regular fixed / percentage components
            $amount = round($this->computeAmount($calcType, $value, $baseSalary), 2);

            if ($amount <= 0) {
                continue;
            }

            $line = [
                'title' => $master->name,
                'slug' => $master->slug,
                'type' => $master->type,
                'calculation_type' => $calcType,
                'value' => $value,
                'amount' => $amount,
            ];

            $master->type === 'earning' ? $earnings[] = $line : $deductions[] = $line;
        }

        // --- 3) Employee custom items (not in master list) ---
        foreach ($employeeComponents as $ec) {
            if ($ec->salary_component_id !== null) {
                continue;
            }

            $amount = round($this->computeAmount($ec->calculation_type, (float) $ec->value, $baseSalary), 2);

            if ($amount <= 0) {
                continue;
            }

            $line = [
                'title' => $ec->title,
                'slug' => null,
                'type' => $ec->type,
                'calculation_type' => $ec->calculation_type,
                'value' => (float) $ec->value,
                'amount' => $amount,
            ];

            $ec->type === 'earning' ? $earnings[] = $line : $deductions[] = $line;
        }

        $grossPay = round(collect($earnings)->sum('amount'), 2);
        $totalDeductions = round(collect($deductions)->sum('amount'), 2);

        return [
            'employee_id' => $employee->id,
            'base_salary' => $baseSalary,
            'currency_code' => $employee->currency_code,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay' => round($grossPay - $totalDeductions, 2),
            'inputs' => [
                'total_working_days' => $totalWorkingDays,
                'unpaid_leave_days' => $unpaidLeaveDays,
                'overtime_hours' => $overtimeHours,
            ],
        ];
    }

    /**
     * (Basic × Percentage) / 100 — otherwise fixed amount.
     */
    private function computeAmount(string $calculationType, float $value, float $baseSalary): float
    {
        return match ($calculationType) {
            'percentage' => ($baseSalary * $value) / 100,
            default => $value,
        };
    }
}