<?php

use App\Models\CompanyProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('page_margin');
        });

        // Ensure at least one company exists for backfill.
        $companyId = DB::table('company_profiles')->orderBy('id')->value('id');
        if (! $companyId) {
            $companyId = DB::table('company_profiles')->insertGetId([
                'company_name' => 'My Company',
                'template_type' => 'modern',
                'primary_color' => '#1D4ED8',
                'accent_color' => '#0EA5E9',
                'font_family' => 'Inter',
                'page_margin' => '18mm',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('company_profiles')->cascadeOnDelete();
        });

        Schema::table('salary_components', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('company_profiles')->cascadeOnDelete();
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('company_profiles')->cascadeOnDelete();
        });

        DB::table('branches')->whereNull('company_id')->update(['company_id' => $companyId]);
        DB::table('salary_components')->whereNull('company_id')->update(['company_id' => $companyId]);
        DB::table('payroll_runs')->whereNull('company_id')->update(['company_id' => $companyId]);
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
