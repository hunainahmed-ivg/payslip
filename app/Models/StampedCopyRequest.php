<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;


class StampedCopyRequest extends Model
{
    protected $appends = ['stamped_pdf_url', 'reason_label'];
    
    protected $fillable = [
        'payslip_id', 'employee_id', 'reason', 'reason_note',
        'status', 'reviewed_by', 'reviewed_at', 'review_note', 'stamped_pdf_path',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStampedPdfUrlAttribute(): ?string
    {
        return $this->stamped_pdf_path ? Storage::url($this->stamped_pdf_path) : null;
    }

    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'visa_application' => 'Visa Application',
            'bank_loan' => 'Bank Loan',
            'embassy' => 'Embassy / Consulate',
            default => 'Other',
        };
    }
    
}