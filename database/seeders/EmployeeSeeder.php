<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $karachi = Branch::where('code', 'BRANCH-KHI-01')->first();
        $accra = Branch::where('code', 'BRANCH-ACC-01')->first();

        $medical = SalaryComponent::where('slug', 'medical_allowance')->first();
        $incomeTax = SalaryComponent::where('slug', 'income_tax')->first();
        $housing = SalaryComponent::where('slug', 'housing_allowance')->first();

        // 1) The exact employee from the architecture doc's Phase 3 JSON example
        $bilal = Employee::firstOrCreate(['employee_code' => 'EMP-8042'], [
            'full_name' => 'Bilal Ahmed',
            'email' => 'bilal.ahmed@northwind.com',
            'department' => 'Operations',
            'designation' => 'Field Supervisor',
            'branch_id' => $karachi?->id,
            'currency_code' => 'PKR',
            'base_salary' => 150000,
            'joined_on' => '2023-04-01',
        ]);

        if ($bilal->salaryComponents()->count() === 0) {
            $bilal->salaryComponents()->createMany([
                [
                    'salary_component_id' => $medical?->id,
                    'title' => 'Medical',
                    'type' => 'earning',
                    'calculation_type' => 'percentage',
                    'value' => 10,
                ],
                [
                    'salary_component_id' => null,   // custom item, not in master list
                    'title' => 'Fuel Conveyance',
                    'type' => 'earning',
                    'calculation_type' => 'fixed',
                    'value' => 15000,
                ],
                [
                    'salary_component_id' => $incomeTax?->id,
                    'title' => 'Income Tax',
                    'type' => 'deduction',
                    'calculation_type' => 'statutory',
                    'value' => 8500,
                ],
            ]);
        }

        // 2) Accra employee with a FIXED housing override (matches the Phase 1 mock payslip)
        $amara = Employee::firstOrCreate(['employee_code' => 'EMP-1001'], [
            'full_name' => 'Amara Mensah',
            'email' => 'amara.mensah@northwind.com',
            'department' => 'Finance',
            'designation' => 'Payroll Officer',
            'branch_id' => $accra?->id,
            'currency_code' => 'GHS',
            'base_salary' => 8500,
            'joined_on' => '2021-01-15',
        ]);

        if ($amara->salaryComponents()->count() === 0) {
            $amara->salaryComponents()->createMany([
                [
                    'salary_component_id' => $housing?->id,
                    'title' => 'Housing Allowance',
                    'type' => 'earning',
                    'calculation_type' => 'fixed',
                    'value' => 1200,   // overrides master default of 10%
                ],
            ]);
        }

        // 3) Employee with NO overrides — will use pure global defaults
        Employee::firstOrCreate(['employee_code' => 'EMP-1002'], [
            'full_name' => 'Kwame Osei',
            'email' => 'kwame.osei@northwind.com',
            'department' => 'Sales',
            'designation' => 'Account Executive',
            'branch_id' => $accra?->id,
            'currency_code' => 'GHS',
            'base_salary' => 6200,
            'joined_on' => '2024-06-01',
        ]);
    }
}