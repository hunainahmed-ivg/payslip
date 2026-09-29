<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 20)->default('admin')->after('email');
            });
        }

        // Existing users are HR/admin operators.
        DB::table('users')->update(['role' => 'admin']);

        if (! Schema::hasColumn('company_profiles', 'tax_brackets')) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->json('tax_brackets')->nullable()->after('page_margin');
            });
        }

        // Allow approved (frozen snapshot, not yet PDF-published) payslip status.
        $this->expandPayslipStatusColumn();
    }

    /**
     * MySQL uses MODIFY; SQLite stores enum as a CHECK constraint and requires a table rebuild.
     */
    private function expandPayslipStatusColumn(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE payslips MODIFY status VARCHAR(20) NOT NULL DEFAULT 'queued'");

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        DB::statement(<<<'SQL'
CREATE TABLE "payslips_new" (
    "id" integer primary key autoincrement not null,
    "payroll_run_id" integer not null,
    "employee_id" integer not null,
    "period" varchar not null,
    "snapshot" text not null,
    "pdf_path" varchar,
    "status" varchar not null default 'queued' check ("status" in ('queued', 'generated', 'published', 'approved')),
    "published_at" datetime,
    "created_at" datetime,
    "updated_at" datetime,
    foreign key("payroll_run_id") references "payroll_runs"("id") on delete cascade,
    foreign key("employee_id") references "employees"("id") on delete cascade
)
SQL);

        DB::statement('INSERT INTO "payslips_new" SELECT * FROM "payslips"');
        DB::statement('DROP TABLE "payslips"');
        DB::statement('ALTER TABLE "payslips_new" RENAME TO "payslips"');
        DB::statement('CREATE UNIQUE INDEX "payslips_employee_id_period_unique" on "payslips" ("employee_id", "period")');

        DB::statement('PRAGMA foreign_keys=ON');
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
