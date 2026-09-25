<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\EmployeeBulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


class EmployeeBulkImportController extends Controller
{
    public function __construct(
        protected EmployeeBulkImportService $service
    ) {}

    /**
     * Download .xlsx template.
     */
    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle('Employees');

        $sheet->fromArray(
            EmployeeBulkImportService::HEADERS,
            null,
            'A1'
        );

        $sheet->fromArray([
            'EMP-9001',
            'John Doe',
            'john.doe@example.com',
            'BRANCH-KHI-01',
            'Engineering',
            'Software Engineer',
            '2026-01-15',
            150000,
            'PKR',
            'yes',
        ], null, 'A2');

        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');

        $instructions->setCellValue('A1', 'Employee Bulk Import Instructions');
        $instructions->setCellValue('A3', '1. Do not change column headers in the Employees sheet.');
        $instructions->setCellValue('A4', '2. employee_code must be unique.');
        $instructions->setCellValue('A5', '3. branch_code must match an existing branch code.');
        $instructions->setCellValue('A6', '4. email is optional but must be valid if provided.');
        $instructions->setCellValue('A7', '5. joined_on format should be YYYY-MM-DD.');
        $instructions->setCellValue('A8', '6. base_salary must be numeric.');
        $instructions->setCellValue('A9', '7. currency_code should be 3 letters, e.g. PKR, USD, GHS.');
        $instructions->setCellValue('A10', '8. is_active can be yes/no, true/false, active/inactive, or 1/0.');

        $writer = new Xlsx($spreadsheet);

        $filename = 'employee-bulk-import-template.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Validate file without saving.
     */
    public function dryRun(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'update_existing' => ['nullable', 'boolean'],
        ]);

        try {
            $report = $this->service->process(
                $request->file('file'),
                dryRun: true,
                updateExisting: $request->boolean('update_existing')
            );
        } catch (\Throwable $e) {
            $report = $this->exceptionReport($e);
        }

        return response()->json($report, $report['valid'] ? 200 : 422);
    }

    /**
     * Commit import after validation.
     */
    public function commit(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'update_existing' => ['nullable', 'boolean'],
        ]);

        try {
            $report = $this->service->process(
                $request->file('file'),
                dryRun: false,
                updateExisting: $request->boolean('update_existing')
            );
        } catch (\Throwable $e) {
            $report = $this->exceptionReport($e);
        }

        if (! ($report['committed'] ?? false)) {
            return response()->json($report, 422);
        }

        AuditLog::record(
            'employee.bulk_import_committed',
            'BULK-EMP-' . now()->format('YmdHis'),
            sprintf(
                'Bulk employee import completed. Created: %d, Updated: %d',
                $report['summary']['created'] ?? 0,
                $report['summary']['updated'] ?? 0
            )
        );

        return response()->json($report);
    }

    private function exceptionReport(\Throwable $e): array
    {
        return [
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
                    'full_name' => null,
                    'status' => 'error',
                    'errors' => [$e->getMessage()],
                ],
            ],
        ];
    }
}