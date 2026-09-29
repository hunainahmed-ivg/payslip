<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\SalaryLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryLedgerEntry>
 */
class SalaryLedgerEntryFactory extends Factory
{
    protected $model = SalaryLedgerEntry::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_id' => CompanyProfile::factory(),
            'event_type' => 'initial',
            'salary_increment_id' => null,
            'previous_basic_salary' => null,
            'basic_salary' => 50000,
            'effective_date' => now()->toDateString(),
            'note' => 'Initial salary',
            'created_by' => null,
        ];
    }
}
