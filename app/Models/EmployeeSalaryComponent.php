<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryComponent extends Model
{
    protected $fillable = [
        'employee_id', 'salary_component_id', 'title',
        'type', 'calculation_type', 'value',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }

    /**
     * Same rule as master components:
     * percentage → (Basic × value) / 100, otherwise fixed amount.
     */
    public function compute(float $basicSalary): float
    {
        return match ($this->calculation_type) {
            'percentage' => ($basicSalary * (float) $this->value) / 100,
            default => (float) $this->value,
        };
    }
}