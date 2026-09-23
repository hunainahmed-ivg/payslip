<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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
     * Download the standardized CSV template.
     */
    public function template()
    {
        $rows = [
            self::TEMPLATE_HEADERS,
            ['EMP-8042', '2026-09', 22, 22, 0, 0, 0, 0],
            ['EMP-1001', '2026-09', 22, 20, 2, 0, 4.5, 1],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'payroll-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Dry-run: validate everything, write NOTHING.
     */
    public function dryRun(Request $request)
    {
        $report = $this->buildReport($this->parseFile($request));

        return redirect()->route('payroll.import')->with('report', $report);
    }

    /**
     * Commit: re-validate, then upsert only if 100% clean.
     */
    public function commit(Request $request)
    {
        $report = $request->session()->get('report');

        if (! $report || ($report['errors'] ?? 1) > 0 || empty($report['rows'])) {
            return redirect()->route('payroll.import')
                ->with('error', 'Run a dry-run validation with zero errors before committing.');
        }

        $committed = 0;

        foreach ($report['rows'] as $row) {
            if ($row['status'] !== 'valid') {
                continue;
            }

            $employee = Employee::where('employee_code', $row['data']['employee_code'])->first();

            if (! $employee) {
                continue;
            }

            PayrollInput::updateOrCreate(
                ['employee_id' => $employee->id, 'period' => $row['data']['period']],
                [
                    'total_working_days' => $row['data']['total_working_days'],
                    'attended_days' => $row['data']['attended_days'],
                    'unpaid_leave_days' => $row['data']['unpaid_leave_days'],
                    'paid_leave_days' => $row['data']['paid_leave_days'],
                    'overtime_hours' => $row['data']['overtime_hours'],
                    'late_count' => $row['data']['late_count'],
                    'source' => 'csv',
                ],
            );

            $committed++;
        }

        // Report consumed — commit button locks again until next dry-run
        $request->session()->forget('report');

        return redirect()->route('payroll.import')
            ->with('success', "Committed {$committed} row(s) successfully. Payroll inputs are ready for Phase 5.");
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