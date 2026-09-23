<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'code', 'name', 'location', 'currency_code', 'currency_symbol', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Will be connected to Employee model in Phase 3
    // public function employees(): HasMany { return $this->hasMany(Employee::class); }
}