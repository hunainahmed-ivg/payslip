<?php

namespace App\Jobs;

use App\Models\Payslip;
use App\Services\PayslipPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeneratePayslipPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payslip $payslip)
    {
    }

    public function handle(PayslipPdfService $service): void
    {
        $service->generate($this->payslip);
    }

}
