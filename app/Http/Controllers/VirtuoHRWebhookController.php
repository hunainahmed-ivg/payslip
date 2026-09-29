<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollInput;
use App\Models\PayrollSyncEvent;
use App\Services\VirtuoHRWebhookVerifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VirtuoHRWebhookController extends Controller
{
    /** Numeric monthly fields mapped from the payload. */
    private const NUMERIC_FIELDS = [
        'total_working_days',
        'attended_days',
        'unpaid_leave_days',
        'paid_leave_days',
        'overtime_hours',
        'late_count',
        'bonus_amount',
        'overtime_pay',
    ];

    public function __construct(
        protected VirtuoHRWebhookVerifier $verifier
    ) {}

    public function sync(Request $request): JsonResponse
    {
        // 1) Authenticate by HMAC BEFORE touching the database.
        $check = $this->verifier->verify($request);

        if (! $check['valid']) {
            // Never persist on auth failure (avoids flooding the log with forged keys).
            return response()->json([
                'valid' => false,
                'message' => $check['message'],
            ], $check['status']);
        }

        $companyId = $check['company_id'] ?? null;
        if ($companyId) {
            \App\Support\CurrentCompany::bind((int) $companyId);
        }

        // 2) Parse JSON body.
        $payload = $request->json()->all();

        if (! is_array($payload)) {
            return response()->json(['valid' => false, 'message' => 'Body must be valid JSON.'], 400);
        }

        $rawBody = $request->getContent();
        $payloadHash = hash('sha256', $rawBody);

        // 3) Resolve idempotency key (client header wins; else deterministic from body).
        $idemHeader = (string) config('services.virtuohr.webhook_idempotency_header', 'Idempotency-Key');
        $key = trim((string) $request->header($idemHeader, ''));
        if ($key === '') {
            $key = 'auto-'.$payloadHash;
        }

        // 4) Replay? Return the stored outcome without re-applying rows.
        $existing = PayrollSyncEvent::where('idempotency_key', $key)->first();

        if ($existing) {
            $body = $existing->response_body ?? ['valid' => $existing->status === 'processed'];
            $body['idempotent_replay'] = true;

            return response()->json($body, (int) $existing->http_status)
                ->header('Idempotent-Replay', 'true');
        }

        // 5) Top-level validation.
        $period = $this->normalizePeriod($payload['period'] ?? null);
        $records = $payload['records'] ?? null;
        $topErrors = [];

        if ($period === null) {
            $topErrors[] = 'Invalid or missing "period". Expected YYYY-MM.';
        }
        if (! is_array($records) || count($records) === 0) {
            $topErrors[] = '"records" must be a non-empty array.';
        }

        if ($topErrors !== []) {
            return $this->logAndRespond($key, $period, $payloadHash, $request, 'rejected', 422, [
                'valid' => false,
                'message' => implode(' ', $topErrors),
                'period' => $period,
                'rows' => [],
                'summary' => ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0],
            ], implode(' ', $topErrors));
        }

        // 6) Per-record validation + build plan.
        $codes = collect($records)
            ->map(fn ($r) => is_array($r) ? $this->str($r['employee_code'] ?? null) : '')
            ->filter()
            ->unique()
            ->values();

        $employeeIds = Employee::query()
            ->whereIn('employee_code', $codes)
            ->when($companyId, fn ($q) => $q->whereHas(
                'branch',
                fn ($b) => $b->where('company_id', $companyId),
            ))
            ->pluck('id', 'employee_code');

        $rows = [];
        $plan = [];
        $seen = [];

        foreach ($records as $i => $rec) {
            $line = $i + 1;

            if (! is_array($rec)) {
                $rows[] = $this->errRow($line, null, ['Record must be an object.']);
                continue;
            }

            $code = $this->str($rec['employee_code'] ?? null);
            $errors = [];

            if ($code === '') {
                $errors[] = 'employee_code is required.';
            } elseif (! isset($employeeIds[$code])) {
                $errors[] = 'Employee code does not exist.';
            }

            if (isset($seen[$code])) {
                $errors[] = 'Duplicate employee_code in the same payload.';
            }
            $seen[$code] = true;

            $nums = [];
            foreach (self::NUMERIC_FIELDS as $f) {
                if (! array_key_exists($f, $rec)) {
                    continue; // absent => leave untouched on update, default on create
                }
                $n = $this->num($rec[$f]);
                if ($n === null) {
                    $errors[] = ucfirst(str_replace('_', ' ', $f)).' must be numeric.';
                } elseif ($n < 0) {
                    $errors[] = ucfirst(str_replace('_', ' ', $f)).' cannot be negative.';
                } else {
                    $nums[$f] = $n;
                }
            }

            // Cross-field checks only when the relevant keys are present.
            $twd = $nums['total_working_days'] ?? null;
            $upd = $nums['unpaid_leave_days'] ?? null;
            $pdl = $nums['paid_leave_days'] ?? null;
            $att = $nums['attended_days'] ?? null;

            if ($twd !== null && $upd !== null && $upd > $twd) {
                $errors[] = 'unpaid_leave_days cannot exceed total_working_days.';
            }
            if ($twd !== null && $pdl !== null && $pdl > $twd) {
                $errors[] = 'paid_leave_days cannot exceed total_working_days.';
            }
            if ($twd !== null && $att !== null && $upd !== null && $pdl !== null
                && ($att + $upd + $pdl) > $twd) {
                $errors[] = 'attended + unpaid + paid cannot exceed total_working_days.';
            }

            if ($errors !== []) {
                $rows[] = $this->errRow($line, $code, $errors);
                continue;
            }

            $rows[] = [
                'line' => $line,
                'employee_code' => $code,
                'status' => 'valid',
                'errors' => [],
            ];
            $plan[] = [
                'employee_id' => $employeeIds[$code] ?? null,
                'period' => $period,
                'nums' => $nums,
            ];
        }

        $failed = count(array_filter($rows, fn ($r) => ($r['status'] ?? '') === 'error'));

        // If anything is invalid, commit NOTHING (mirror the CSV/Excel import semantics)
        // so VirtuoHR can correct and resend (a corrected body yields a new key/hash).
        if ($failed > 0) {
            return $this->logAndRespond($key, $period, $payloadHash, $request, 'rejected', 422, [
                'valid' => false,
                'period' => $period,
                'summary' => ['total' => count($records), 'created' => 0, 'updated' => 0, 'failed' => $failed],
                'rows' => $rows,
            ], $failed.' record(s) failed validation; nothing was written.');
        }

        // 7) Apply (non-destructive upsert) inside a transaction.
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($plan, &$created, &$updated) {
            foreach ($plan as $p) {
                $existing = PayrollInput::where('employee_id', $p['employee_id'])
                    ->where('period', $p['period'])
                    ->first();

                if ($existing) {
                    // Only overwrite the fields the payload actually included.
                    $existing->fill($p['nums'] + ['source' => 'api']);
                    $existing->save();
                    $updated++;
                } else {
                    PayrollInput::create([
                        'employee_id' => $p['employee_id'],
                        'period' => $p['period'],
                        'total_working_days' => $p['nums']['total_working_days'] ?? 22,
                        'attended_days' => $p['nums']['attended_days'] ?? null,
                        'unpaid_leave_days' => $p['nums']['unpaid_leave_days'] ?? 0,
                        'paid_leave_days' => $p['nums']['paid_leave_days'] ?? 0,
                        'overtime_hours' => $p['nums']['overtime_hours'] ?? 0,
                        'late_count' => $p['nums']['late_count'] ?? 0,
                        'bonus_amount' => $p['nums']['bonus_amount'] ?? 0,
                        'overtime_pay' => $p['nums']['overtime_pay'] ?? 0,
                        'source' => 'api',
                    ]);
                    $created++;
                }
            }
        });

        $body = [
            'valid' => true,
            'period' => $period,
            'summary' => [
                'total' => count($records),
                'created' => $created,
                'updated' => $updated,
                'failed' => 0,
            ],
            'rows' => $rows,
            'received_at' => now()->toIso8601String(),
        ];

        return $this->logAndRespond($key, $period, $payloadHash, $request, 'processed', 200, $body, null, [
            'records_total' => count($records),
            'records_created' => $created,
            'records_updated' => $updated,
            'records_failed' => 0,
        ]);
    }

    private function logAndRespond(
        string $key,
        ?string $period,
        string $payloadHash,
        Request $request,
        string $status,
        int $httpStatus,
        array $body,
        ?string $error,
        array $counts = []
    ): JsonResponse {
        try {
            PayrollSyncEvent::create([
                'company_id' => $companyId ?? \App\Support\CurrentCompany::id(),
                'idempotency_key' => $key,
                'period' => $period,
                'source' => 'api',
                'signature_valid' => true,
                'status' => $status,
                'records_total' => $counts['records_total'] ?? 0,
                'records_created' => $counts['records_created'] ?? 0,
                'records_updated' => $counts['records_updated'] ?? 0,
                'records_failed' => $counts['records_failed'] ?? 0,
                'http_status' => $httpStatus,
                'response_body' => $body,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'payload_hash' => $payloadHash,
                'error_message' => $error,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique-key race: another identical delivery won. Treat as replay.
            $dupe = PayrollSyncEvent::where('idempotency_key', $key)->first();
            if ($dupe) {
                $rb = $dupe->response_body ?? $body;
                $rb['idempotent_replay'] = true;
                return response()->json($rb, (int) $dupe->http_status)->header('Idempotent-Replay', 'true');
            }
        }

        return response()->json($body, $httpStatus);
    }

    private function normalizePeriod($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }
        try {
            return Carbon::parse($value)->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function str($value): string
    {
        return $value === null ? '' : trim((string) $value);
    }

    private function num($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = str_replace([',', ' '], '', (string) $value);
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function errRow(int $line, ?string $code, array $errors): array
    {
        return ['line' => $line, 'employee_code' => $code, 'status' => 'error', 'errors' => $errors];
    }
}