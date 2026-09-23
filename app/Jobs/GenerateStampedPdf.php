<?php

namespace App\Jobs;

use App\Models\StampedCopyRequest;
use App\Services\PayslipPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateStampedPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public StampedCopyRequest $stampedRequest)
    {
    }

    public function handle(PayslipPdfService $service): void
    {
        $service->generateStamped($this->stampedRequest);
    }
}