<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('company_profiles')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->foreignId('salary_increment_id')->nullable()->constrained('salary_increments')->nullOnDelete();
            $table->decimal('previous_basic_salary', 12, 2)->nullable();
            $table->decimal('basic_salary', 12, 2);
            $table->date('effective_date');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
            $table->index(['company_id', 'effective_date']);
            $table->index(['company_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_ledger_entries');
    }
};
