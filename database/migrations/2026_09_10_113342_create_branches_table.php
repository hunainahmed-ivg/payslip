<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();              // e.g. BRANCH-KHI-01
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('currency_code', 3)->default('USD');   // ISO 4217
            $table->string('currency_symbol', 5)->default('$');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};