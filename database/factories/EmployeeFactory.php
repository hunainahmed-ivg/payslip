<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'employee_code' => 'EMP-'.fake()->unique()->numerify('####'),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'department' => 'Engineering',
            'designation' => 'Engineer',
            'branch_id' => Branch::factory(),
            'currency_code' => 'PKR',
            'base_salary' => 50000,
            'joined_on' => now()->subYear()->toDateString(),
            'is_active' => true,
            'profile_picture_path' => null,
        ];
    }
}
