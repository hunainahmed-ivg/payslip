<?php

namespace App\Models;

use Database\Factories\SalaryIncrementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalaryIncrement extends Model
{
    /** @use HasFactory<SalaryIncrementFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'company_id',
        'previous_basic_salary',
        'increment_type',
        'value',
        'new_basic_salary',
        'effective_date',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_basic_salary' => 'decimal:2',
            'value' => 'decimal:4',
            'new_basic_salary' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(SalaryLedgerEntry::class);
    }
}
