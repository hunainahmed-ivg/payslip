<?php

namespace App\Models;

use Database\Factories\RegistrationDocumentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationDocumentType extends Model
{
    /** @use HasFactory<RegistrationDocumentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'title',
        'type',
        'required',
        'allow_front_back',
        'profile_pic_required',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'allow_front_back' => 'boolean',
            'profile_pic_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        return $query->when($companyId, fn ($q) => $q->where('company_id', $companyId));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
