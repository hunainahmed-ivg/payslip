<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeeBulkImportService
{
    public const HEADERS = [
        'employee_code',
        'full_name',
        'email',
        'branch_code',
        'department',
        'designation',
        'joined_on',
        'base_salary',
        'currency_code',
        'is_active',
    ];

    /**
     * Process employee bulk import file.
     */
    public function process(UploadedFile $file, bool $dryRun = true, bool $updateExisting = false): array
    {
        $rows = $this->parseFile($file);

        $branchCodes = collect($rows)
            ->map(fn ($row) => $this->normalizeString($row['raw']['branch_code'] ?? null))
            ->filter()
            ->unique()
            ->values();

        $branches = Branch::query()
            ->whereIn('code', $branchCodes)
            ->pluck('id', 'code');

        $reportRows = [];
        $validCount = 0;
        $createdCount = 0;
        $updatedCount = 0;
        $seenEmployeeCodes = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            $raw = $row['raw'];

            $employeeCode = $this->normalizeString($raw['employee_code'] ?? null);
            $fullName = $this->normalizeString($raw['full_name'] ?? null);
            $email = $this->normalizeString($raw['email'] ?? null);
            $branchCode = $this->normalizeString($raw['branch_code'] ?? null);
            $department = $this->normalizeString($raw['department'] ?? null);
            $designation = $this->normalizeString($raw['designation'] ?? null);
            $joinedOn = $this->normalizeDate($raw['joined_on'] ?? null);
            $baseSalary = $this->normalizeNumeric($raw['base_salary'] ?? null);
            $currencyCode = strtoupper($this->normalizeString($raw['currency_code'] ?? null));
            $isActive = $this->normalizeBoolean($raw['is_active'] ?? null);

            $errors = [];

            $existingEmployee = null;

            if ($employeeCode === '') {
                $errors[] = 'Employee code is required.';
            } else {
                $existingEmployee = Employee::query()
                    ->where('employee_code', $employeeCode)
                    ->first();

                if (isset($seenEmployeeCodes[$employeeCode])) {
                    $errors[] = 'Duplicate employee_code in the same file.';
                }

                $seenEmployeeCodes[$employeeCode] = true;

                if ($existingEmployee && ! $updateExisting) {
                    $errors[] = 'Employee code already exists. Enable update mode to modify existing employee.';
                }
            }

            if ($fullName === '') {
                $errors[] = 'Full name is required.';
            }

            if ($branchCode === '') {
                $errors[] = 'Branch code is required.';
            } elseif (! isset($branches[$branchCode])) {
                $errors[] = 'Branch code does not exist.';
            }

            if ($email !== '') {
                $emailExists = Employee::query()
                    ->where('email', $email)
                    ->when($existingEmployee, function ($query, $existing) {
                        $query->whereKeyNot($existing->id);
                    })
                    ->exists();

                if ($emailExists) {
                    $errors[] = 'Email already exists for another employee.';
                }
            }

            if ($baseSalary === null) {
                $errors[] = 'Base salary is required and must be numeric.';
            } elseif ($baseSalary < 0) {
                $errors[] = 'Base salary cannot be negative.';
            }

            if ($currencyCode === '') {
                $errors[] = 'Currency code is required.';
            } elseif (strlen($currencyCode) !== 3) {
                $errors[] = 'Currency code must be 3 characters, e.g. PKR.';
            }

            if ($isActive === null) {
                $errors[] = 'is_active must be yes/no, true/false, active/inactive, or 1/0.';
            }

            $validator = Validator::make([
                'employee_code' => $employeeCode,
                'full_name' => $fullName,
                'email' => $email,
                'branch_code' => $branchCode,
                'department' => $department,
                'designation' => $designation,
                'joined_on' => $joinedOn,
                'base_salary' => $baseSalary,
                'currency_code' => $currencyCode,
                'is_active' => $isActive,
            ], [
                'employee_code' => ['required', 'string', 'max:50'],
                'full_name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'branch_code' => ['required', 'string', 'max:50'],
                'department' => ['nullable', 'string', 'max:255'],
                'designation' => ['nullable', 'string', 'max:255'],
                'joined_on' => ['nullable', 'date'],
                'base_salary' => ['required', 'numeric', 'min:0'],
                'currency_code' => ['required', 'string', 'size:3'],
                'is_active' => ['nullable', 'boolean'],
            ]);

            foreach ($validator->errors()->all() as $validationError) {
                if (! in_array($validationError, $errors, true)) {
                    $errors[] = $validationError;
                }
            }

            if (count($errors) === 0) {
                $validCount++;
            }

            $reportRows[] = [
                'line' => $line,
                'employee_code' => $employeeCode,
                'full_name' => $fullName,
                'email' => $email,
                'branch_code' => $branchCode,
                'status' => count($errors) === 0 ? 'valid' : 'error',
                'errors' => $errors,
                'data' => [
                    'employee_code' => $employeeCode,
                    'full_name' => $fullName,
                    'email' => $email !== '' ? $email : null,
                    'branch_id' => $branches[$branchCode] ?? null,
                    'department' => $department !== '' ? $department : null,
                    'designation' => $designation !== '' ? $designation : null,
                    'joined_on' => $joinedOn,
                    'base_salary' => $baseSalary,
                    'currency_code' => $currencyCode,
                    'is_active' => (bool) $isActive,
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

        DB::transaction(function () use ($reportRows, $updateExisting, &$createdCount, &$updatedCount) {
            foreach ($reportRows as $row) {
                if ($row['status'] !== 'valid') {
                    continue;
                }

                $data = $row['data'];

                $existing = Employee::query()
                    ->where('employee_code', $data['employee_code'])
                    ->first();

                if ($existing) {
                    if (! $updateExisting) {
                        continue;
                    }

                    $existing->update($data);
                    $updatedCount++;
                } else {
                    Employee::query()->create($data);
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

            if (isset($rowData['joined_on']) && is_numeric($rowData['joined_on'])) {
                try {
                    $date = ExcelDate::excelToDateTimeObject((float) $rowData['joined_on']);
                    $rowData['joined_on'] = $date->format('Y-m-d');
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

        // Remove UTF-8 BOM if present.
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

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function normalizeBoolean($value): ?bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'active' => true,
            '0', 'false', 'no', 'n', 'inactive' => false,
            default => null,
        };
    }
}