<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    protected $appends = ['pdf_url', 'pdf_download_url'];
    
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'period',
        'snapshot', 'pdf_path', 'status', 'published_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'published_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function getPdfUrlAttribute(): ?string
    {
        if (! $this->pdf_path || $this->status !== 'published') {
            return null;
        }

        return route('portal.payslips.view', $this);
    }

    public function getPdfDownloadUrlAttribute(): ?string
    {
        if (! $this->pdf_path || $this->status !== 'published') {
            return null;
        }

        return route('portal.payslips.download', $this);
    }
    
    public function stampedRequests(): HasMany
    {
        return $this->hasMany(StampedCopyRequest::class);
    }
}