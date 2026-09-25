<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PayslipPdfService
{
    public function generate(Payslip $payslip): void
    {
        $payslip->load('employee');

        $profile = CompanyProfile::first();

        $html = view('payslips.template', [
            'payslip' => $payslip,
            'snapshot' => $payslip->snapshot,
            'profile' => $profile,
            // DOMPDF reads images from absolute filesystem paths
            'headerPath' => $profile?->header_image_path
                ? Storage::disk('public')->path($profile->header_image_path)
                : null,
            'footerPath' => $profile?->footer_image_path
                ? Storage::disk('public')->path($profile->footer_image_path)
                : null,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $path = 'payslips/'.$payslip->period.'/'.$payslip->employee->employee_code.'.pdf';

        Storage::disk('private')->makeDirectory(dirname($path), 0755, true, true);

        Storage::disk('private')->put($path, $pdf->output());

        $payslip->update([
            'pdf_path' => $path,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

        /**
     * Phase 7: Stamped & watermarked verified copy over the FROZEN snapshot.
     */
        public function generateStamped(\App\Models\StampedCopyRequest $stampedRequest): void
        {
            $payslip = $stampedRequest->payslip;
            $payslip->load('employee');
    
            $profile = CompanyProfile::first();
    
            $html = view('payslips.template', [
                'payslip' => $payslip,
                'snapshot' => $payslip->snapshot,
                'profile' => $profile,
                'headerPath' => $profile?->header_image_path
                    ? str_replace('\\', '/', Storage::disk('public')->path($profile->header_image_path))
                    : null,
                'footerPath' => $profile?->footer_image_path
                    ? str_replace('\\', '/', Storage::disk('public')->path($profile->footer_image_path))
                    : null,
                'stamped' => true,
                'stamp' => [
                    'reference' => 'REQ-'.str_pad((string) $stampedRequest->id, 5, '0', STR_PAD_LEFT),
                    'reason' => $stampedRequest->reason_label,
                    'approved_by' => $stampedRequest->reviewer?->name ?? 'HR Department',
                    'approved_at' => ($stampedRequest->reviewed_at ?? now())->toFormattedDateString(),
                    'company' => $profile?->company_name ?? 'The Company',
                ],
            ])->render();
    
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
                
            $path = 'payslips/stamped/'.$payslip->period.'/'.$payslip->employee->employee_code.'-stamped-'.$stampedRequest->id.'.pdf';

            Storage::disk('private')->makeDirectory(dirname($path), 0755, true, true);

            Storage::disk('private')->put($path, $pdf->output());
    
            $stampedRequest->update(['stamped_pdf_path' => $path]);
        }
}