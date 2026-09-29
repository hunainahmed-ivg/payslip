<?php

namespace App\Models;

use Database\Factories\SalaryLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryLedgerEntry extends Model
{
    /** @use HasFactory<SalaryLedgerEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'company_id',
        'event_type',
        'salary_increment_id',
        'previous_basic_salary',
        'basic_salary',
        'effective_date',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_basic_salary' => 'decimal:2',
            'basic_salary' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_id');
    }

    public function salaryIncrement(): BelongsTo
    {
        return $this->belongsTo(SalaryIncrement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
