<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class SalaryStructureSeeder extends Seeder
{
    public function run(): void
    {
        // Branches (multi-currency, ISO 4217)
        Branch::firstOrCreate(['code' => 'BRANCH-ACC-01'], [
            'name' => 'Accra Head Office',
            'location' => 'Accra, Ghana',
            'currency_code' => 'GHS',
            'currency_symbol' => 'GH₵',
        ]);

        Branch::firstOrCreate(['code' => 'BRANCH-KHI-01'], [
            'name' => 'Karachi Branch',
            'location' => 'Karachi, Pakistan',
            'currency_code' => 'PKR',
            'currency_symbol' => '₨',
        ]);

        // Master salary components (global default rules)
        $components = [
            // --- Earnings ---
            ['name' => 'Basic Salary', 'slug' => 'basic_salary', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_value' => 0, 'is_taxable' => true, 'description' => 'Core contract salary. Exact value stored per employee in Phase 3.'],
            ['name' => 'Housing Allowance', 'slug' => 'housing_allowance', 'type' => 'earning', 'calculation_type' => 'percentage', 'default_value' => 10, 'is_taxable' => true, 'description' => 'Default 10% of basic salary.'],
            ['name' => 'Transport Allowance', 'slug' => 'transport_allowance', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_value' => 650, 'is_taxable' => true, 'description' => 'Fixed monthly transport amount.'],
            ['name' => 'Medical Allowance', 'slug' => 'medical_allowance', 'type' => 'earning', 'calculation_type' => 'percentage', 'default_value' => 5, 'is_taxable' => false, 'description' => 'Non-taxable medical support (5% of basic).'],
            // --- Deductions ---
            ['name' => 'SSNIT / Pension (13.5%)', 'slug' => 'pension', 'type' => 'deduction', 'calculation_type' => 'percentage', 'default_value' => 13.5, 'is_taxable' => false, 'description' => 'Statutory pension contribution.'],
            ['name' => 'Income Tax', 'slug' => 'income_tax', 'type' => 'deduction', 'calculation_type' => 'statutory', 'default_value' => 0, 'is_taxable' => false, 'description' => 'Computed by statutory tax rules during payroll run.'],
            ['name' => 'Unpaid Leave', 'slug' => 'unpaid_leave', 'type' => 'deduction', 'calculation_type' => 'statutory', 'default_value' => 0, 'is_taxable' => false, 'description' => '(Basic / total working days) × unpaid leave days.'],
            ['name' => 'Loan / Advance Recovery', 'slug' => 'loan_recovery', 'type' => 'deduction', 'calculation_type' => 'fixed', 'default_value' => 0, 'is_taxable' => false, 'description' => 'Monthly recovery against issued loans or advances.'],
        ];

        foreach ($components as $component) {
            SalaryComponent::firstOrCreate(['slug' => $component['slug']], $component);
        }
    }
}