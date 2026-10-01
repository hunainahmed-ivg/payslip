<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // HR/Admin account — created without UserFactory so seeding works
        // when fakerphp/faker is not installed (it is require-dev only).
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'company_id' => null,
            ],
        );

        // Project structure & demo data
        $this->call([
            SalaryStructureSeeder::class,
            EmployeeSeeder::class,
            PortalUserSeeder::class,
            TenantOperatorSeeder::class,
        ]);
    }
}
