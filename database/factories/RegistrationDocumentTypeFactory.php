<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use App\Models\RegistrationDocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationDocumentType>
 */
class RegistrationDocumentTypeFactory extends Factory
{
    protected $model = RegistrationDocumentType::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyProfile::factory(),
            'title' => fake()->randomElement(['CNIC', 'Passport', 'Degree Certificate']),
            'type' => fake()->unique()->slug(2),
            'required' => true,
            'allow_front_back' => false,
            'profile_pic_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
