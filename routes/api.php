<?php

use App\Http\Controllers\Api\PayrollSyncController;
use Illuminate\Support\Facades\Route;

// Phase 4: VirtuoHR Webhook Endpoint
Route::post('/v1/payroll/sync', PayrollSyncController::class);