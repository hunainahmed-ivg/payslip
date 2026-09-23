<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('My Company');
            $table->string('tax_id')->nullable();
            $table->string('registration_number')->nullable();
            $table->text('address')->nullable();
            $table->string('header_image_path')->nullable();
            $table->string('footer_image_path')->nullable();
            $table->enum('template_type', ['modern', 'classic', 'compact', 'custom'])->default('modern');
            $table->longText('custom_html')->nullable();
            $table->string('primary_color', 7)->default('#4F46E5');
            $table->string('accent_color', 7)->default('#0EA5E9');
            $table->string('font_family')->default('Inter');
            $table->string('page_margin')->default('18mm');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};