<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // HR/Admin account — created without UserFactory so seeding works
        // when fakerphp/faker is not installed (it is require-dev only).
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
            ],
        );

        // Project structure & demo data
        $this->call([
            SalaryStructureSeeder::class,
            EmployeeSeeder::class,
            PortalUserSeeder::class,
        ]);
    }
}
