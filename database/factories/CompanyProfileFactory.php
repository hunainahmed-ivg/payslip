<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    protected $model = CompanyProfile::class;

    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'tax_id' => fake()->numerify('TAX-######'),
            'registration_number' => fake()->numerify('REG-######'),
            'address' => fake()->address(),
            'template_type' => 'classic',
            'is_active' => true,
        ];
    }
}
