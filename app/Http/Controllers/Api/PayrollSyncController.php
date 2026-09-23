<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayrollSyncController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // 1. Shared-secret authentication (Bearer token ya X-API-Key header)
        $token = $request->bearerToken() ?? $request->header('X-API-Key');
        
        if ($token !== config('services.virtuohr.token')) {
            return response()->json(['error' => 'Unauthorized - Invalid API Token'], 401);
        }

        // 2. JSON Payload Validation
        $validator = Validator::make($request->all(), [
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.employee_code' => ['required', 'string', 'exists:employees,employee_code'],
            'records.*.total_working_days' => ['required', 'integer', 'between:1,31'],
            'records.*.attended_days' => ['nullable', 'integer', 'between:0,31'],
            'records.*.unpaid_leave_days' => ['nullable', 'numeric', 'min:0'],
            'records.*.paid_leave_days' => ['nullable', 'numeric', 'min:0'],
            'records.*.overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'records.*.late_count' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 3. Upsert Data (Update if exists, Create if new)
        $period = $request->input('period');
        $synced = 0;

        foreach ($request->input('records') as $record) {
            $employee = Employee::where('employee_code', $record['employee_code'])->first();
            if (! $employee) {
                continue;
            }

            PayrollInput::updateOrCreate(
                ['employee_id' => $employee->id, 'period' => $period],
                [
                    'total_working_days' => $record['total_working_days'],
                    'attended_days' => $record['attended_days'] ?? null,
                    'unpaid_leave_days' => $record['unpaid_leave_days'] ?? 0,
                    'paid_leave_days' => $record['paid_leave_days'] ?? 0,
                    'overtime_hours' => $record['overtime_hours'] ?? 0,
                    'late_count' => $record['late_count'] ?? 0,
                    'source' => 'api', // Marked as API source
                ]
            );
            $synced++;
        }

        return response()->json([
            'message' => 'Sync successful',
            'period' => $period,
            'synced_records' => $synced,
        ], 200);
    }
}