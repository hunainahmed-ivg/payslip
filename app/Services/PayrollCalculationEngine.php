<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\PayrollInput;
use App\Models\SalaryComponent;
use App\Support\CurrentCompany;

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

        $input = PayrollInput::where('employee_id', $employee->id)
            ->where('period', $period)
            ->first();

        $totalWorkingDays = (float) ($input?->total_working_days ?? 22);
        $unpaidLeaveDays = (float) ($input?->unpaid_leave_days ?? 0);
        $overtimeHours = (float) ($input?->overtime_hours ?? 0);

        $unpaidLeaveDays = (float) ($overrides['unpaid_leave_days'] ?? $unpaidLeaveDays);
        $overtimeHours = (float) ($overrides['overtime_hours'] ?? $overtimeHours);

        $employeeComponents = $employee->salaryComponents()->get()->keyBy(
            fn ($ec) => $ec->salary_component_id ?? 'custom-'.$ec->id,
        );

        $earnings = [];
        $deductions = [];
        $taxableGross = 0.0;

        $earnings[] = [
            'title' => 'Basic Salary',
            'slug' => 'basic_salary',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'value' => $baseSalary,
            'amount' => round($baseSalary, 2),
            'is_taxable' => true,
        ];
        $taxableGross += $baseSalary;

        $masterComponents = SalaryComponent::active()
            ->forCompany($employee->branch?->company_id ?? \App\Support\CurrentCompany::id())
            ->where('slug', '!=', 'basic_salary')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $incomeTaxMaster = null;

        foreach ($masterComponents as $master) {
            if ($master->slug === 'income_tax') {
                $incomeTaxMaster = $master;
                continue;
            }

            $override = $employeeComponents->get($master->id);

            $calcType = $override?->calculation_type ?? $master->calculation_type;
            $value = (float) ($override?->value ?? $master->default_value);

            if ($master->slug === 'unpaid_leave') {
                $amount = $totalWorkingDays > 0
                    ? round(($baseSalary / $totalWorkingDays) * $unpaidLeaveDays, 2)
                    : 0.0;

                if ($amount <= 0) {
                    continue;
                }

                $deductions[] = [
                    'title' => $master->name,
                    'slug' => $master->slug,
                    'type' => 'deduction',
                    'calculation_type' => 'statutory',
                    'value' => $unpaidLeaveDays,
                    'amount' => $amount,
                    'is_taxable' => false,
                ];

                continue;
            }

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
                'is_taxable' => (bool) $master->is_taxable,
            ];

            if ($master->type === 'earning') {
                $earnings[] = $line;
                if ($master->is_taxable) {
                    $taxableGross += $amount;
                }
            } else {
                $deductions[] = $line;
            }
        }

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
                'is_taxable' => true,
            ];

            if ($ec->type === 'earning') {
                $earnings[] = $line;
                $taxableGross += $amount;
            } else {
                $deductions[] = $line;
            }
        }

        $overtimePay = (float) ($overrides['overtime_pay'] ?? ($input?->overtime_pay ?? 0));

        if ($overtimePay > 0) {
            $earnings[] = [
                'title' => 'Overtime Pay',
                'slug' => 'overtime_pay',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'value' => $overtimePay,
                'amount' => round($overtimePay, 2),
                'is_taxable' => true,
            ];
            $taxableGross += $overtimePay;
        } elseif ($overtimeHours > 0) {
            $hourlyRate = $baseSalary / max(1, $totalWorkingDays * 8);
            $overtimeAmount = round($hourlyRate * $overtimeHours * 1.5, 2);

            if ($overtimeAmount > 0) {
                $earnings[] = [
                    'title' => 'Overtime',
                    'slug' => 'overtime',
                    'type' => 'earning',
                    'calculation_type' => 'fixed',
                    'value' => $overtimeHours,
                    'amount' => $overtimeAmount,
                    'is_taxable' => true,
                ];
                $taxableGross += $overtimeAmount;
            }
        }

        $bonusAmount = (float) ($overrides['bonus_amount'] ?? ($input?->bonus_amount ?? 0));

        if ($bonusAmount > 0) {
            $earnings[] = [
                'title' => 'Bonus',
                'slug' => 'bonus',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'value' => $bonusAmount,
                'amount' => round($bonusAmount, 2),
                'is_taxable' => true,
            ];
            $taxableGross += $bonusAmount;
        }

        // Income tax: fixed employee override OR progressive company tax brackets
        if ($incomeTaxMaster) {
            $taxOverride = $employeeComponents->get($incomeTaxMaster->id);
            $taxAmount = 0.0;

            if ($taxOverride && (float) $taxOverride->value > 0) {
                $taxAmount = round((float) $taxOverride->value, 2);
            } else {
                $taxAmount = $this->computeTaxFromBrackets($taxableGross);
            }

            if ($taxAmount > 0) {
                $deductions[] = [
                    'title' => $incomeTaxMaster->name,
                    'slug' => $incomeTaxMaster->slug,
                    'type' => 'deduction',
                    'calculation_type' => 'statutory',
                    'value' => $taxAmount,
                    'amount' => $taxAmount,
                    'is_taxable' => false,
                ];
            }
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
            'taxable_gross' => round($taxableGross, 2),
            'inputs' => [
                'total_working_days' => $totalWorkingDays,
                'unpaid_leave_days' => $unpaidLeaveDays,
                'overtime_hours' => $overtimeHours,
                'overtime_pay' => $overtimePay,
                'bonus_amount' => $bonusAmount,
            ],
        ];
    }

    /**
     * Progressive tax from company_profiles.tax_brackets JSON.
     * Each bracket: { "from": 0, "to": 50000, "rate": 5 } (to null = unlimited).
     */
    private function computeTaxFromBrackets(float $taxableIncome): float
    {
        $profile = \App\Support\CurrentCompany::profile();
        $brackets = $profile?->tax_brackets;

        if (! is_array($brackets) || $brackets === []) {
            return 0.0;
        }

        usort($brackets, fn ($a, $b) => ((float) ($a['from'] ?? 0)) <=> ((float) ($b['from'] ?? 0)));

        $tax = 0.0;

        foreach ($brackets as $bracket) {
            $from = (float) ($bracket['from'] ?? 0);
            $to = array_key_exists('to', $bracket) && $bracket['to'] !== null && $bracket['to'] !== ''
                ? (float) $bracket['to']
                : null;
            $rate = (float) ($bracket['rate'] ?? 0);

            if ($taxableIncome <= $from || $rate <= 0) {
                continue;
            }

            $upper = $to === null ? $taxableIncome : min($taxableIncome, $to);
            $segment = max(0, $upper - $from);
            $tax += ($segment * $rate) / 100;
        }

        return round($tax, 2);
    }

    private function computeAmount(string $calculationType, float $value, float $baseSalary): float
    {
        return match ($calculationType) {
            'percentage' => ($baseSalary * $value) / 100,
            default => $value,
        };
    }
}
