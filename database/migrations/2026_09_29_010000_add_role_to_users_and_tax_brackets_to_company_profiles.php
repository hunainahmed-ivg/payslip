<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('admin')->after('email');
        });

        // Existing users are HR/admin operators.
        DB::table('users')->update(['role' => 'admin']);

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->json('tax_brackets')->nullable()->after('page_margin');
        });

        // Allow approved (frozen snapshot, not yet PDF-published) payslip status.
        DB::statement("ALTER TABLE payslips MODIFY status VARCHAR(20) NOT NULL DEFAULT 'queued'");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('tax_brackets');
        });
    }
};
