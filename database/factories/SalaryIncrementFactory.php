<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\SalaryIncrement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryIncrement>
 */
class SalaryIncrementFactory extends Factory
{
    protected $model = SalaryIncrement::class;

    public function definition(): array
    {
        $previous = 50000;
        $value = 10;
        $new = round($previous * 1.1, 2);

        return [
            'employee_id' => Employee::factory(),
            'company_id' => CompanyProfile::factory(),
            'previous_basic_salary' => $previous,
            'increment_type' => 'percent',
            'value' => $value,
            'new_basic_salary' => $new,
            'effective_date' => now()->toDateString(),
            'note' => 'Annual increment',
            'created_by' => null,
        ];
    }
}
