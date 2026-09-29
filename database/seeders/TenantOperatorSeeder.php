<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Example: one platform admin plus per-company tenant admins for API/dashboard isolation demos.
 */
class TenantOperatorSeeder extends Seeder
{
    public function run(): void
    {
        $companies = CompanyProfile::query()->orderBy('id')->get();

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'company_id' => null,
                'assigned_permissions' => null,
            ],
        );

        foreach ($companies as $index => $company) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $company->company_name) ?: 'company-'.$company->id);
            $email = 'admin+'.$slug.'@example.com';

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $company->company_name.' Admin',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'company_id' => $company->id,
                    'assigned_permissions' => null,
                ],
            );
        }
    }
}
