<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);                      // YYYY-MM
            $table->unsignedTinyInteger('total_working_days')->default(22);
            $table->unsignedTinyInteger('attended_days')->nullable();
            $table->decimal('unpaid_leave_days', 5, 2)->default(0);
            $table->decimal('paid_leave_days', 5, 2)->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->unsignedInteger('late_count')->default(0);
            $table->enum('source', ['manual', 'csv', 'api'])->default('csv');
            $table->timestamps();
            $table->unique(['employee_id', 'period']);        // one record per employee per month
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_inputs');
    }
};