<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'base_salary', 'currency_code',
        'earnings', 'deductions', 'gross_pay', 'total_deductions', 'net_pay', 'overrides',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'earnings' => 'array',
        'deductions' => 'array',
        'gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'overrides' => 'array',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}