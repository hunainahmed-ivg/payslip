<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollInput;
use App\Models\PayrollSyncEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\MonthlyPayrollImportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollImportController extends Controller
{
    public const TEMPLATE_HEADERS = [
        'employee_code', 'period', 'total_working_days', 'attended_days',
        'unpaid_leave_days', 'paid_leave_days', 'overtime_hours', 'late_count',
    ];

    /**
     * Import page (Step 4.2 UI).
     */
    public function create(): Response
    {
        return Inertia::render('Payroll/Import', [
            'employeeCount' => Employee::where('is_active', true)->count(),
            'recent' => PayrollInput::with('employee')->latest()->limit(10)->get(),
            'report' => session('report'),
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }

    /**
     * VirtuoHR sync status for a selected period (push webhook based).
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $period = $validated['period'];

        return response()->json([
            'period' => $period,
            'api_records' => PayrollInput::where('period', $period)->where('source', 'api')->count(),
            'csv_records' => PayrollInput::where('period', $period)->where('source', 'csv')->count(),
            'last_event' => PayrollSyncEvent::where('period', $period)->latest()->first(),
        ]);
    }

    /**
     * Download the standardized CSV template.
     */
    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle('Monthly Payroll Import');

        $sheet->fromArray(
            MonthlyPayrollImportService::HEADERS,
            null,
            'A1'
        );

        $sheet->fromArray([
            'EMP-8042',
            '2026-10',
            26,
            24,
            2,
            0,
            4.5,
            1,
        ], null, 'A2');

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');

        $instructions->setCellValue('A1', 'Monthly Payroll Import Instructions');
        $instructions->setCellValue('A3', '1. Do not change column headers in the Monthly Payroll Import sheet.');
        $instructions->setCellValue('A4', '2. employee_code must match an existing employee.');
        $instructions->setCellValue('A5', '3. period must be in YYYY-MM format, e.g. 2026-10.');
        $instructions->setCellValue('A6', '4. total_working_days, attended_days, unpaid_leave_days, paid_leave_days, overtime_hours, late_count must be numeric.');
        $instructions->setCellValue('A7', '5. This import updates or creates monthly payroll input records for the given employee and period.');
        $instructions->setCellValue('A8', '6. Do not upload base salary here. Base salary belongs to employee master data.');

        $writer = new Xlsx($spreadsheet);

        $filename = 'monthly-payroll-import-template.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Dry-run: validate everything, write NOTHING.
     */
    public function dryRun(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            $report = app(MonthlyPayrollImportService::class)->process(
                $request->file('file'),
                dryRun: true
            );
        } catch (\Throwable $e) {
            $report = [
                'valid' => false,
                'dry_run' => true,
                'summary' => [
                    'total' => 0,
                    'valid' => 0,
                    'errors' => 1,
                    'created' => 0,
                    'updated' => 0,
                    'failed' => 1,
                ],
                'rows' => [
                    [
                        'line' => 0,
                        'employee_code' => null,
                        'period' => null,
                        'status' => 'error',
                        'errors' => [$e->getMessage()],
                    ],
                ],
            ];
        }

        if ($request->expectsJson()) {
            return response()->json($report, $report['valid'] ? 200 : 422);
        }

        return back()->with('importReport', $report);
    }

    /**
     * Commit: re-validate, then upsert only if 100% clean.
     */
    public function commit(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            $report = app(MonthlyPayrollImportService::class)->process(
                $request->file('file'),
                dryRun: false
            );
        } catch (\Throwable $e) {
            $report = [
                'valid' => false,
                'dry_run' => false,
                'summary' => [
                    'total' => 0,
                    'valid' => 0,
                    'errors' => 1,
                    'created' => 0,
                    'updated' => 0,
                    'failed' => 1,
                ],
                'rows' => [
                    [
                        'line' => 0,
                        'employee_code' => null,
                        'period' => null,
                        'status' => 'error',
                        'errors' => [$e->getMessage()],
                    ],
                ],
            ];
        }

        if (! ($report['committed'] ?? false)) {
            if ($request->expectsJson()) {
                return response()->json($report, 422);
            }

            return back()
                ->with('importReport', $report)
                ->withErrors(['file' => 'Import failed because validation errors exist.']);
        }

        if (class_exists(\App\Models\AuditLog::class) && method_exists(\App\Models\AuditLog::class, 'record')) {
            \App\Models\AuditLog::record(
                'payroll_input.bulk_import_committed',
                'BULK-PAYROLL-' . now()->format('YmdHis'),
                sprintf(
                    'Monthly payroll import completed. Created: %d, Updated: %d',
                    $report['summary']['created'] ?? 0,
                    $report['summary']['updated'] ?? 0
                )
            );
        }

        if ($request->expectsJson()) {
            return response()->json($report);
        }

        return back()->with(
            'success',
            sprintf(
                'Monthly payroll import completed. Created: %d, Updated: %d',
                $report['summary']['created'] ?? 0,
                $report['summary']['updated'] ?? 0
            )
        );
    }

    /**
     * Parse + validate CSV into a dry-run report.
     */
    private function parseFile(Request $request): array
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        // 👇 FIX: fopen() returns a real stream resource that fgetcsv() accepts
        $handle = fopen($request->file('file')->getPathname(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Unable to read the uploaded file.']);
        }

        $header = null;
        $records = [];
        $line = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($line === 1) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $row);
                continue;
            }

            if ($row === [null] || $row === ['']) {
                continue; // skip blank lines
            }

            $records[] = [
                'line' => $line,
                'raw' => array_combine($header ?? [], array_pad($row, count($header ?? []), null)),
            ];
        }

        fclose($handle);

        if ($header === null) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        if ($header !== self::TEMPLATE_HEADERS) {
            throw ValidationException::withMessages([
                'file' => 'CSV header mismatch. Expected: '.implode(', ', self::TEMPLATE_HEADERS).'. Download the template below.',
            ]);
        }

        if ($records === []) {
            throw ValidationException::withMessages(['file' => 'The file contains no data rows.']);
        }

        return $records;
    }

    private function buildReport(array $records): array
    {
        $codes = collect($records)->map(fn ($r) => trim((string) ($r['raw']['employee_code'] ?? '')))->filter()->unique();
        $employees = Employee::whereIn('employee_code', $codes)->get()->keyBy('employee_code');

        $rows = [];
        $seen = [];
        $valid = 0;

        foreach ($records as $record) {
            $raw = $record['raw'];
            $code = trim((string) ($raw['employee_code'] ?? ''));
            $period = trim((string) ($raw['period'] ?? ''));

            $validator = Validator::make($raw, [
                'employee_code' => ['required', 'string', 'exists:employees,employee_code'],
                'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
                'total_working_days' => ['required', 'integer', 'between:1,31'],
                'attended_days' => ['nullable', 'integer', 'between:0,31'],
                'unpaid_leave_days' => ['nullable', 'numeric', 'between:0,31'],
                'paid_leave_days' => ['nullable', 'numeric', 'between:0,31'],
                'overtime_hours' => ['nullable', 'numeric', 'between:0,744'],
                'late_count' => ['nullable', 'integer', 'min:0'],
            ]);

            $errors = $validator->errors()->all();

            $key = $code.'|'.$period;
            if (isset($seen[$key])) {
                $errors[] = 'Duplicate employee_code + period combination inside the file.';
            }
            $seen[$key] = true;

            $total = (int) ($raw['total_working_days'] ?? 0);
            $unpaid = (float) ($raw['unpaid_leave_days'] ?? 0);
            $paid = (float) ($raw['paid_leave_days'] ?? 0);

            if ($errors === [] && ($unpaid + $paid) > $total) {
                $errors[] = 'Unpaid + paid leave days cannot exceed total working days.';
            }

            $status = $errors === [] ? 'valid' : 'error';
            if ($status === 'valid') {
                $valid++;
            }

            $rows[] = [
                'line' => $record['line'],
                'status' => $status,
                'errors' => $errors,
                'employee_name' => $employees[$code]->full_name ?? null,
                'data' => [
                    'employee_code' => $code,
                    'period' => $period,
                    'total_working_days' => $total,
                    'attended_days' => ($raw['attended_days'] ?? '') !== '' && $raw['attended_days'] !== null ? (int) $raw['attended_days'] : null,
                    'unpaid_leave_days' => $unpaid,
                    'paid_leave_days' => $paid,
                    'overtime_hours' => (float) ($raw['overtime_hours'] ?? 0),
                    'late_count' => (int) ($raw['late_count'] ?? 0),
                ],
            ];
        }

        return [
            'total' => count($rows),
            'valid' => $valid,
            'errors' => count($rows) - $valid,
            'rows' => $rows,
        ];
    }
}