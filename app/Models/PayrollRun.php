<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'period', 'status', 'total_earnings', 'total_deductions', 'total_net_pay',
        'generated_by', 'generated_at', 'approved_at',
    ];

    protected $casts = [
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    
    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    protected static function booted(): void
    {
        static::updating(function (self $run) {
            $originalStatus = $run->getOriginal('status');

            $protectedStatuses = [
                'approved',
                'locked',
                'published',
            ];

            if (! in_array($originalStatus, $protectedStatuses, true)) {
                return;
            }

            $dirtyFields = collect($run->getDirty())
                ->keys()
                ->reject(function ($key) {
                    return in_array($key, ['status', 'updated_at'], true);
                })
                ->values();

            if ($dirtyFields->isNotEmpty()) {
                throw new \RuntimeException(
                    'Approved payroll runs cannot be edited. Attempted fields: '
                    . $dirtyFields->implode(', ')
                );
            }

            if ($run->status !== $originalStatus) {
                $allowedNextStatuses = match ($originalStatus) {
                    'approved' => ['locked', 'published'],
                    'locked' => ['published'],
                    default => [],
                };

                if (! in_array($run->status, $allowedNextStatuses, true)) {
                    throw new \RuntimeException(
                        "Cannot change payroll run status from {$originalStatus} to {$run->status}."
                    );
                }
            }
        });
    }
}