<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$privateDisk = Illuminate\Support\Facades\Storage::disk('private');
$publicDisk = Illuminate\Support\Facades\Storage::disk('public');

$payslips = App\Models\Payslip::query()
    ->with('employee')
    ->where('status', 'published')
    ->get(['id', 'employee_id', 'period', 'status', 'pdf_path']);

if ($payslips->isEmpty()) {
    echo "No published payslips found.\n";
    exit;
}

foreach ($payslips as $payslip) {
    echo "\nChecking payslip #{$payslip->id} ({$payslip->period})\n";

    if (! $payslip->pdf_path) {
        echo " - pdf_path is NULL, regenerating...\n";
    } elseif ($privateDisk->exists($payslip->pdf_path)) {
        echo " - OK: " . $privateDisk->path($payslip->pdf_path) . "\n";
        continue;
    } elseif ($publicDisk->exists($payslip->pdf_path)) {
        echo " - Found in public disk, moving to private...\n";

        $contents = $publicDisk->get($payslip->pdf_path);

        $privateDisk->makeDirectory(
            dirname($payslip->pdf_path),
            0755,
            true,
            true
        );

        $privateDisk->put($payslip->pdf_path, $contents);

        $publicDisk->delete($payslip->pdf_path);

        echo " - Moved to private storage.\n";
        continue;
    } else {
        echo " - File missing, regenerating...\n";
    }

    $generated = false;

    if (class_exists(\App\Jobs\GeneratePayslipPdf::class)) {
        try {
            Illuminate\Support\Facades\Bus::dispatchSync(
                new \App\Jobs\GeneratePayslipPdf($payslip)
            );

            $generated = true;
            echo " - Used job: App\\Jobs\\GeneratePayslipPdf\n";
        } catch (\Throwable $e) {
            echo " - Job failed: " . $e->getMessage() . "\n";
        }
    }

    if (! $generated && class_exists(\App\Services\PayslipPdfService::class)) {
        try {
            app(\App\Services\PayslipPdfService::class)->generate($payslip);

            $generated = true;
            echo " - Used service: App\\Services\\PayslipPdfService\n";
        } catch (\Throwable $e) {
            echo " - Service failed: " . $e->getMessage() . "\n";
        }
    }

    if (! $generated) {
        echo " - No payslip PDF generator found.\n";
    }

    $payslip->refresh();

    if ($payslip->pdf_path && $privateDisk->exists($payslip->pdf_path)) {
        echo " - Regenerated OK: " . $privateDisk->path($payslip->pdf_path) . "\n";
    } else {
        echo " - Still missing. pdf_path = " . var_export($payslip->pdf_path, true) . "\n";
    }
}

echo "\nDone.\n";