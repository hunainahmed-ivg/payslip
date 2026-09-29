<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('role')
                ->constrained('company_profiles')
                ->nullOnDelete();
            $table->json('assigned_permissions')->nullable()->after('company_id');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('is_active');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('tokenable_id')
                ->constrained('company_profiles')
                ->cascadeOnDelete();
        });

        if (Schema::hasTable('payroll_sync_events')) {
            Schema::table('payroll_sync_events', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_sync_events', 'company_id')) {
                    $table->foreignId('company_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('company_profiles')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_sync_events') && Schema::hasColumn('payroll_sync_events', 'company_id')) {
            Schema::table('payroll_sync_events', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('assigned_permissions');
        });
    }
};
