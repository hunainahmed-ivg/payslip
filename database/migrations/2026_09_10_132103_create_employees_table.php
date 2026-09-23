<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();   // e.g. EMP-8042
            $table->string('full_name');
            $table->string('email')->nullable()->unique();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);           // employee-level ISO 4217 override
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->date('joined_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};