<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->json('snapshot');          // immutable frozen line-items copy
            $table->string('pdf_path')->nullable();
            $table->enum('status', ['queued', 'generated', 'published'])->default('queued');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'period']);   // one payslip per employee per month
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};