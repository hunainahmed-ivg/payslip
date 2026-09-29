<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'employee_code', 'full_name', 'email', 'department', 'designation',
        'branch_id', 'currency_code', 'base_salary', 'joined_on', 'is_active',
        'profile_picture_path',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'joined_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stampedRequests(): HasMany
    {
        return $this->hasMany(StampedCopyRequest::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function salaryIncrements(): HasMany
    {
        return $this->hasMany(SalaryIncrement::class);
    }

    public function salaryLedgerEntries(): HasMany
    {
        return $this->hasMany(SalaryLedgerEntry::class);
    }
}
