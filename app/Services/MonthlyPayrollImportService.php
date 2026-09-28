<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollInput;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MonthlyPayrollImportService
{
    public const HEADERS = [
        'employee_code',
        'period',
        'total_working_days',
        'attended_days',
        'unpaid_leave_days',
        'paid_leave_days',
        'overtime_hours',
        'late_count',
        'bonus_amount',
        'overtime_pay',
    ];

    public function process(UploadedFile $file, bool $dryRun = true): array
    {
        $rows = $this->parseFile($file);

        $employeeCodes = collect($rows)
            ->map(fn ($row) => $this->normalizeString($row['raw']['employee_code'] ?? null))
            ->filter()
            ->unique()
            ->values();

        $employees = Employee::query()
            ->whereIn('employee_code', $employeeCodes)
            ->pluck('id', 'employee_code');

        $reportRows = [];
        $validCount = 0;
        $createdCount = 0;
        $updatedCount = 0;

        foreach ($rows as $row) {
            $line = $row['line'];
            $raw = $row['raw'];

            $employeeCode = $this->normalizeString($raw['employee_code'] ?? null);
            $period = $this->normalizePeriod($raw['period'] ?? null);
            $totalWorkingDays = $this->normalizeNumeric($raw['total_working_days'] ?? null);
            $attendedDays = $this->normalizeNumeric($raw['attended_days'] ?? null);
            $unpaidLeaveDays = $this->normalizeNumeric($raw['unpaid_leave_days'] ?? null);
            $paidLeaveDays = $this->normalizeNumeric($raw['paid_leave_days'] ?? null);
            $overtimeHours = $this->normalizeNumeric($raw['overtime_hours'] ?? null);
            $lateCount = $this->normalizeNumeric($raw['late_count'] ?? null);

            $errors = [];

            if ($employeeCode === '') {
                $errors[] = 'Employee code is required.';
            } elseif (! isset($employees[$employeeCode])) {
                $errors[] = 'Employee code does not exist.';
            }

            if ($period === null) {
                $errors[] = 'Invalid period format. Expected YYYY-MM, e.g. 2026-10.';
            }

            if ($totalWorkingDays === null) {
                $errors[] = 'Total working days is required and must be numeric.';
            } elseif ($totalWorkingDays < 0) {
                $errors[] = 'Total working days cannot be negative.';
            }

            if ($attendedDays === null) {
                $errors[] = 'Attended days is required and must be numeric.';
            } elseif ($attendedDays < 0) {
                $errors[] = 'Attended days cannot be negative.';
            }

            if ($unpaidLeaveDays === null) {
                $errors[] = 'Unpaid leave days is required and must be numeric.';
            } elseif ($unpaidLeaveDays < 0) {
                $errors[] = 'Unpaid leave days cannot be negative.';
            }

            if ($paidLeaveDays === null) {
                $errors[] = 'Paid leave days is required and must be numeric.';
            } elseif ($paidLeaveDays < 0) {
                $errors[] = 'Paid leave days cannot be negative.';
            }

            if ($overtimeHours === null) {
                $errors[] = 'Overtime hours is required and must be numeric.';
            } elseif ($overtimeHours < 0) {
                $errors[] = 'Overtime hours cannot be negative.';
            }

            if ($lateCount === null) {
                $errors[] = 'Late count is required and must be numeric.';
            } elseif ($lateCount < 0) {
                $errors[] = 'Late count cannot be negative.';
            }

            if (
                $totalWorkingDays !== null
                && $unpaidLeaveDays !== null
                && $unpaidLeaveDays > $totalWorkingDays
            ) {
                $errors[] = 'Unpaid leave days cannot be greater than total working days.';
            }

            if (
                $totalWorkingDays !== null
                && $paidLeaveDays !== null
                && $paidLeaveDays > $totalWorkingDays
            ) {
                $errors[] = 'Paid leave days cannot be greater than total working days.';
            }

            if (
                $totalWorkingDays !== null
                && $attendedDays !== null
                && $unpaidLeaveDays !== null
                && $paidLeaveDays !== null
                && ($attendedDays + $unpaidLeaveDays + $paidLeaveDays) > $totalWorkingDays
            ) {
                $errors[] = 'Attended days + unpaid leave + paid leave cannot exceed total working days.';
            }

            // bonus_amount and overtime_pay are OPTIONAL. Blank = 0.
            $bonusAmount = 0.0;
            $overtimePay = 0.0;

            $bonusRaw = $this->normalizeString($raw['bonus_amount'] ?? null);
            if ($bonusRaw !== '') {
                $n = $this->normalizeNumeric($bonusRaw);
                if ($n === null) {
                    $errors[] = 'Bonus amount must be numeric.';
                } elseif ($n < 0) {
                    $errors[] = 'Bonus amount cannot be negative.';
                } else {
                    $bonusAmount = $n;
                }
            }

            $overtimePayRaw = $this->normalizeString($raw['overtime_pay'] ?? null);
            if ($overtimePayRaw !== '') {
                $n = $this->normalizeNumeric($overtimePayRaw);
                if ($n === null) {
                    $errors[] = 'Overtime pay must be numeric.';
                } elseif ($n < 0) {
                    $errors[] = 'Overtime pay cannot be negative.';
                } else {
                    $overtimePay = $n;
                }
            }
            
            if (count($errors) === 0) {
                $validCount++;
            }

            $reportRows[] = [
                'line' => $line,
                'employee_code' => $employeeCode,
                'period' => $period,
                'status' => count($errors) === 0 ? 'valid' : 'error',
                'errors' => $errors,
                'data' => [
                    'employee_id' => $employees[$employeeCode] ?? null,
                    'period' => $period,
                    'total_working_days' => $totalWorkingDays,
                    'attended_days' => $attendedDays,
                    'unpaid_leave_days' => $unpaidLeaveDays,
                    'paid_leave_days' => $paidLeaveDays,
                    'overtime_hours' => $overtimeHours,
                    'late_count' => $lateCount,
                    'bonus_amount' => $bonusAmount,
                    'overtime_pay' => $overtimePay,
                ],
            ];
        }

        $report = [
            'valid' => count($reportRows) > 0 && $validCount === count($reportRows),
            'dry_run' => $dryRun,
            'summary' => [
                'total' => count($reportRows),
                'valid' => $validCount,
                'errors' => count($reportRows) - $validCount,
                'created' => 0,
                'updated' => 0,
                'failed' => count($reportRows) - $validCount,
            ],
            'rows' => $reportRows,
        ];

        if ($dryRun) {
            return $report;
        }

        if ($report['summary']['errors'] > 0) {
            return $report;
        }

        DB::transaction(function () use ($reportRows, &$createdCount, &$updatedCount) {
            foreach ($reportRows as $row) {
                if ($row['status'] !== 'valid') {
                    continue;
                }

                $data = $row['data'];

                $existing = PayrollInput::query()
                    ->where('employee_id', $data['employee_id'])
                    ->where('period', $data['period'])
                    ->first();

                if ($existing) {
                    $existing->update($data);
                    $updatedCount++;
                } else {
                    PayrollInput::query()->create($data);
                    $createdCount++;
                }
            }
        });

        $report['summary']['created'] = $createdCount;
        $report['summary']['updated'] = $updatedCount;
        $report['summary']['failed'] = 0;
        $report['committed'] = true;

        return $report;
    }

    private function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseSpreadsheet($file);
        }

        if ($extension === 'csv') {
            return $this->parseCsv($file);
        }

        throw new \InvalidArgumentException('Unsupported file type. Please upload .xlsx, .xls, or .csv.');
    }

    private function parseSpreadsheet(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $allRows = $sheet->toArray(null, true, true, 'A');

        if (empty($allRows)) {
            throw new \InvalidArgumentException('Uploaded file is empty.');
        }

        $headerRow = array_shift($allRows);

        $header = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            $headerRow ?? []
        );

        $this->assertHeaders($header);

        $records = [];

        foreach ($allRows as $index => $row) {
            $line = $index + 2;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $row = array_slice($row, 0, count($header));
            $row = array_pad($row, count($header), null);

            $rowData = array_combine($header, $row);

            if (isset($rowData['period']) && is_numeric($rowData['period'])) {
                try {
                    $date = ExcelDate::excelToDateTimeObject((float) $rowData['period']);
                    $rowData['period'] = $date->format('Y-m');
                } catch (\Throwable $e) {
                    // Leave as-is and let validation handle it.
                }
            }

            $records[] = [
                'line' => $line,
                'raw' => $rowData,
            ];
        }

        return $records;
    }

    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new \InvalidArgumentException('Unable to read uploaded CSV file.');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            throw new \InvalidArgumentException('Uploaded CSV file is empty.');
        }

        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        }

        $header = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            $header
        );

        $this->assertHeaders($header);

        $records = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $row = array_slice($row, 0, count($header));
            $row = array_pad($row, count($header), null);

            $records[] = [
                'line' => $line,
                'raw' => array_combine($header, $row),
            ];
        }

        fclose($handle);

        return $records;
    }

    private function assertHeaders(array $header): void
    {
        if ($header !== self::HEADERS) {
            throw new \InvalidArgumentException(
                'Invalid template. Expected columns: ' . implode(', ', self::HEADERS)
            );
        }
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeString($value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeNumeric($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = str_replace([',', ' '], '', (string) $value);

        if (is_numeric($clean)) {
            return (float) $clean;
        }

        return null;
    }

    private function normalizePeriod($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m');
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{4}\/\d{1,2}$/', $value)) {
            $parts = explode('/', $value);
            return $parts[0] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        }

        try {
            return Carbon::parse($value)->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }
    }
}