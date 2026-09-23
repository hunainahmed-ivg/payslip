<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'calculation_type',
        'default_value', 'is_taxable', 'is_active', 'description',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'default_value' => 'decimal:2',
    ];

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

    /**
     * Phase 2 calculation rule from the architecture doc:
     * Fixed → use amount as-is. Percentage → (Basic × Percentage) / 100.
     */
    public function compute(float $basicSalary): float
    {
        return match ($this->calculation_type) {
            'percentage' => ($basicSalary * (float) $this->default_value) / 100,
            default => (float) $this->default_value,
        };
    }
}