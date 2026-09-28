<?php

namespace App\Mail;

use App\Models\Employee;
use App\Models\Payslip;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayslipPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?Employee $employee;
    public string $periodLabel;

    public function __construct(public Payslip $payslip)
    {
        $this->employee = $payslip->employee;
        $this->periodLabel = Carbon::parse($payslip->period . '-01')->format('F Y');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your payslip for {$this->periodLabel} is now available",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payslip-published',
        );
    }
}