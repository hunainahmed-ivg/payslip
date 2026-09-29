<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyProfile::factory(),
            'code' => strtoupper(fake()->unique()->lexify('BR???')),
            'name' => fake()->city().' Branch',
            'location' => fake()->city(),
            'currency_code' => 'PKR',
            'currency_symbol' => 'Rs',
            'is_active' => true,
        ];
    }
}
