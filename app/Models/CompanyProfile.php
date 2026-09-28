<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    protected $fillable = [
        'company_name', 'tax_id', 'registration_number', 'address',
        'header_image_path', 'footer_image_path',
        'template_type', 'custom_html',
        'primary_color', 'accent_color', 'font_family', 'page_margin',
        'tax_brackets', 'is_active',
    ];

    protected $casts = [
        'tax_brackets' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = ['header_image_url', 'footer_image_url'];

    public function getHeaderImageUrlAttribute(): ?string
    {
        return $this->header_image_path ? Storage::url($this->header_image_path) : null;
    }

    public function getFooterImageUrlAttribute(): ?string
    {
        return $this->footer_image_path ? Storage::url($this->footer_image_path) : null;
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'company_id');
    }

    public function salaryComponents(): HasMany
    {
        return $this->hasMany(SalaryComponent::class, 'company_id');
    }

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'company_id');
    }

    public function employees(): HasManyThrough
    {
        return $this->hasManyThrough(Employee::class, Branch::class, 'company_id', 'branch_id');
    }
}
