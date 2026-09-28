<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryComponent extends Model
{
    protected $fillable = [
        'company_id', 'name', 'slug', 'type', 'calculation_type',
        'default_value', 'is_taxable', 'is_active', 'description',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'default_value' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_id');
    }

    public function scopeEarnings($query)
    {
        return $query->where('type', 'earning');
    }

    public function scopeDeductions($query)
    {
        return $query->where('type', 'deduction');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        return $query->when($companyId, fn ($q) => $q->where('company_id', $companyId));
    }

    public function compute(float $basicSalary): float
    {
        return match ($this->calculation_type) {
            'percentage' => ($basicSalary * (float) $this->default_value) / 100,
            default => (float) $this->default_value,
        };
    }
}
