<?php

namespace App\Models;

use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'company_name', 'tax_id', 'registration_number', 'address',
        'header_image_path', 'footer_image_path',
        'template_type', 'custom_html',
        'primary_color', 'accent_color', 'font_family', 'page_margin',
        'tax_brackets', 'is_active', 'webhook_secret',
    ];

    protected $casts = [
        'tax_brackets' => 'array',
        'webhook_secret' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected $appends = ['header_image_url', 'footer_image_url'];

    public function getHeaderImageUrlAttribute(): ?string
    {
        return $this->publicStorageUrl($this->header_image_path);
    }

    public function getFooterImageUrlAttribute(): ?string
    {
        return $this->publicStorageUrl($this->footer_image_path);
    }

    /**
     * Scheme-relative storage URL so letterheads work on http and https
     * regardless of APP_URL (port 1607 is served over plain HTTP).
     */
    private function publicStorageUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return '/storage/'.ltrim($path, '/');
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
