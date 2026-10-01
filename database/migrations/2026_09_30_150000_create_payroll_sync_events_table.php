<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_sync_events')) {
            return;
        }

        Schema::create('payroll_sync_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('company_profiles')
                ->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('period', 7)->nullable()->index();
            $table->string('source')->default('api');
            $table->boolean('signature_valid')->default(false);
            $table->string('status');
            $table->unsignedInteger('records_total')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('response_body')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_sync_events');
    }
};
