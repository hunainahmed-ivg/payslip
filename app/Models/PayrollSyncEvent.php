<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSyncEvent extends Model
{
    protected $fillable = [
        'company_id',
        'idempotency_key',
        'period',
        'source',
        'signature_valid',
        'status',
        'records_total',
        'records_created',
        'records_updated',
        'records_failed',
        'http_status',
        'response_body',
        'ip_address',
        'user_agent',
        'payload_hash',
        'error_message',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'response_body' => 'array',
        'records_total' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_failed' => 'integer',
        'http_status' => 'integer',
    ];
}