<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PortalUserSeeder extends Seeder
{
    public function run(): void
    {
        $portalUsers = [
            ['name' => 'Bilal Ahmed', 'email' => 'bilal.ahmed@northwind.com'],
            ['name' => 'Amara Mensah', 'email' => 'amara.mensah@northwind.com'],
            ['name' => 'Kwame Osei', 'email' => 'kwame.osei@northwind.com'],
        ];

        foreach ($portalUsers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make('password')],
            );

            Employee::where('email', $data['email'])->update(['user_id' => $user->id]);
        }
    }
}