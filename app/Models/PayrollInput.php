<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollInput extends Model
{
    protected $fillable = [
        'employee_id', 'period', 'total_working_days', 'attended_days',
        'unpaid_leave_days', 'paid_leave_days', 'overtime_hours', 'late_count', 'source',
    ];

    protected $casts = [
        'unpaid_leave_days' => 'decimal:2',
        'paid_leave_days' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}