<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PayrollRun;
use App\Models\PayrollRunItem;

echo "=== Payroll Lock Test ===\n";

$approvedRun = PayrollRun::query()
    ->whereIn('status', ['approved', 'locked', 'published'])
    ->first();

if (! $approvedRun) {
    echo "No approved/locked/published payroll run found. Skipping run lock test.\n";
} else {
    echo "Testing payroll run #{$approvedRun->id} with status: {$approvedRun->status}\n";

    try {
        $approvedRun->status = 'draft';
        $approvedRun->save();

        echo "FAIL: Approved payroll run was changed back to draft.\n";
    } catch (\Throwable $e) {
        echo "PASS: Approved payroll run could not be changed.\n";
        echo "Message: " . $e->getMessage() . "\n";
    }
}

echo "\n";

$protectedItem = PayrollRunItem::query()
    ->whereHas('payrollRun', function ($query) {
        $query->whereIn('status', ['approved', 'locked', 'published']);
    })
    ->first();

if (! $protectedItem) {
    echo "No payroll run item found under approved/locked/published run. Skipping item lock test.\n";
} else {
    echo "Testing payroll run item #{$protectedItem->id}\n";

    try {
        $protectedItem->save();

        echo "FAIL: Payroll run item was saved under non-draft payroll run.\n";
    } catch (\Throwable $e) {
        echo "PASS: Payroll run item could not be modified.\n";
        echo "Message: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
