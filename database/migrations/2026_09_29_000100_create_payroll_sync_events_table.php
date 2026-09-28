<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_inputs', 'bonus_amount')) {
            Schema::table('payroll_inputs', function (Blueprint $table) {
                $table->decimal('bonus_amount', 14, 2)->default(0)->after('late_count');
            });
        }

        if (! Schema::hasColumn('payroll_inputs', 'overtime_pay')) {
            Schema::table('payroll_inputs', function (Blueprint $table) {
                $table->decimal('overtime_pay', 14, 2)->default(0)->after('bonus_amount');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payroll_inputs', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_inputs', 'overtime_pay')) {
                $table->dropColumn('overtime_pay');
            }
            if (Schema::hasColumn('payroll_inputs', 'bonus_amount')) {
                $table->dropColumn('bonus_amount');
            }
        });
    }
};