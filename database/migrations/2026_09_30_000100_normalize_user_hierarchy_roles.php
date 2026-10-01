<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        // Current Admin with no company → Super Admin (platform-wide).
        DB::table('users')
            ->whereNull('company_id')
            ->whereIn('role', ['admin', 'hr', 'administrator', 'super-admin'])
            ->update(['role' => UserRole::SuperAdmin->value]);

        // Current Admin tied to a company → Company Admin.
        DB::table('users')
            ->whereNotNull('company_id')
            ->whereIn('role', ['admin', 'hr', 'administrator', 'super-admin', 'company-admin'])
            ->update(['role' => UserRole::CompanyAdmin->value]);

        // Normalize alternate spellings already used as employee.
        DB::table('users')
            ->where('role', 'Employee')
            ->update(['role' => UserRole::Employee->value]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')
            ->where('role', UserRole::SuperAdmin->value)
            ->update(['role' => UserRole::LEGACY_ADMIN]);

        DB::table('users')
            ->where('role', UserRole::CompanyAdmin->value)
            ->update(['role' => UserRole::LEGACY_ADMIN]);
    }
};
