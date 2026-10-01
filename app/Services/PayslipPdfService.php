<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\StampedCopyRequest;
use App\Models\Payslip;
use App\Mail\PayslipPublishedMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class PayslipPdfService
{
    public function generate(Payslip $payslip): void
    {
        $payslip->load('employee');

        if (empty($payslip->snapshot) || ! is_array($payslip->snapshot)) {
            throw new \RuntimeException(
                'Payslip snapshot is missing. Refusing to render PDF from live data.'
            );
        }

        $html = $this->renderHtml($payslip);

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $path = 'payslips/'.$payslip->period.'/'.$payslip->employee->employee_code.'.pdf';

        Storage::disk('private')->makeDirectory(dirname($path), 0755, true, true);

        Storage::disk('private')->put($path, $pdf->output());

        $payslip->update([
            'pdf_path' => $path,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $payslip->loadMissing('employee');

        if ($payslip->employee?->email) {
            Mail::to($payslip->employee->email)->queue(
                new PayslipPublishedMail($payslip)
            );
        }
    }

    /**
     * Render the selected payslip HTML template from the frozen snapshot.
     */
    public function renderHtml(Payslip $payslip, bool $stamped = false, array $stamp = [], bool $forBrowser = false): string
    {
        if (empty($payslip->snapshot) || ! is_array($payslip->snapshot)) {
            throw new \RuntimeException('Payslip snapshot is missing.');
        }

        $profile = $this->resolveCompanyProfile($payslip);
        $view = $this->resolveTemplateView($profile);

        $headerPath = null;
        $footerPath = null;

        if ($profile?->header_image_path && Storage::disk('public')->exists($profile->header_image_path)) {
            $headerPath = $forBrowser
                ? $this->publicStorageUrl($profile->header_image_path)
                : $this->embeddedImageSrc($profile->header_image_path);
        }

        if ($profile?->footer_image_path && Storage::disk('public')->exists($profile->footer_image_path)) {
            $footerPath = $forBrowser
                ? $this->publicStorageUrl($profile->footer_image_path)
                : $this->embeddedImageSrc($profile->footer_image_path);
        }

        return view($view, [
            'payslip' => $payslip,
            'snapshot' => $payslip->snapshot,
            'profile' => $profile,
            'headerPath' => $headerPath,
            'footerPath' => $footerPath,
            'stamped' => $stamped,
            'stamp' => $stamp,
        ])->render();
    }

    private function resolveCompanyProfile(Payslip $payslip): ?CompanyProfile
    {
        $payslip->loadMissing('employee.branch', 'payrollRun');

        $companyId = $payslip->payrollRun?->company_id
            ?? $payslip->employee?->branch?->company_id;

        if ($companyId) {
            return CompanyProfile::query()->find($companyId);
        }

        return CompanyProfile::query()->orderBy('id')->first();
    }

    private function resolveTemplateView(?CompanyProfile $profile): string
    {
        $type = $profile?->template_type ?? 'modern';

        return match ($type) {
            'classic' => 'payslips.classic',
            'compact' => 'payslips.compact',
            'custom' => filled($profile?->custom_html)
                ? 'payslips.custom'
                : 'payslips.modern',
            default => 'payslips.modern',
        };
    }

    /**
     * Browser-safe relative URL so letterheads load on http and https
     * even when APP_URL scheme/host does not match the request.
     */
    private function publicStorageUrl(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    /**
     * Embed public-disk images as data URIs so DomPDF reliably includes them
     * without depending on chroot, remote fetching, or file:// quirks.
     */
    private function embeddedImageSrc(string $path): string
    {
        $absolute = Storage::disk('public')->path($path);
        $mime = mime_content_type($absolute) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($absolute));

        return 'data:'.$mime.';base64,'.$data;
    }

    /**
     * Overlay a digital stamp/watermark onto the ORIGINAL locked payslip PDF.
     */
    public function generateStamped(StampedCopyRequest $stampedRequest): void
    {
        $payslip = $stampedRequest->payslip;

        if (! $payslip) {
            throw new \RuntimeException('Stamped request has no linked payslip.');
        }

        $snapshot = $payslip->snapshot;
        if (empty($snapshot) || ! is_array($snapshot)) {
            throw new \RuntimeException('Payslip snapshot missing; refusing to stamp.');
        }

        $originalPath = $payslip->pdf_path;
        if (! $originalPath || ! Storage::disk('private')->exists($originalPath)) {
            throw new \RuntimeException(
                'Original payslip PDF not found in private storage: '.var_export($originalPath, true)
            );
        }

        $stampedRequest->loadMissing('reviewer', 'employee');
        $payslip->loadMissing('employee.branch');

        $profile = $this->resolveCompanyProfile($payslip);
        $company = $profile->company_name ?? ($snapshot['company_name'] ?? 'Company');
        $reference = 'REQ-'.str_pad((string) $stampedRequest->id, 5, '0', STR_PAD_LEFT);
        $reason = $stampedRequest->reason_label ?? $stampedRequest->reason ?? 'Official use';
        $approvedBy = $stampedRequest->reviewer?->name ?? 'HR Department';
        $approvedAt = ($stampedRequest->reviewed_at ?? now())->format('d M Y');

        $sourceAbsolute = Storage::disk('private')->path($originalPath);

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCellPadding(0);

        $pageCount = $pdf->setSourceFile($sourceAbsolute);

        for ($p = 1; $p <= $pageCount; $p++) {
            $tplId = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tplId);
            $w = (float) $size['width'];
            $h = (float) $size['height'];

            $pdf->AddPage('P', [$w, $h]);
            $pdf->useTemplate($tplId, 0, 0, $w, $h, true);

            $pdf->SetAlpha(0.10);
            $pdf->SetFont('helvetica', 'B', max(26, (int) round($w / 7)));
            $pdf->SetTextColor(15, 23, 42);
            $pdf->StartTransform();
            $pdf->Rotate(-35, $w / 2, $h / 2);
            $pdf->Text($w * 0.05, $h / 2, 'VERIFIED COPY');
            $pdf->StopTransform();
            $pdf->SetAlpha(1);

            if ($p === 1) {
                $boxW = 80;
                $boxH = 42;
                $boxX = $w - $boxW - 12;
                $boxY = $h - $boxH - 16;

                $pdf->SetDrawColor(22, 163, 74);
                $pdf->SetLineWidth(0.7);
                $pdf->Rect($boxX, $boxY, $boxW, $boxH);
                $pdf->SetLineWidth(0.3);
                $pdf->Rect($boxX + 1.6, $boxY + 1.6, $boxW - 3.2, $boxH - 3.2);

                $pdf->SetTextColor(22, 163, 74);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY($boxX + 3, $boxY + 3);
                $pdf->Cell($boxW - 6, 4, $company, 0, 0, 'L');

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetXY($boxX + 3, $boxY + 7.5);
                $pdf->Cell($boxW - 6, 5, 'OFFICIALLY VERIFIED', 0, 0, 'L');

                $pdf->SetTextColor(15, 23, 42);
                $pdf->SetFont('helvetica', '', 7.5);
                $lines = [
                    'Ref: '.$reference,
                    'Purpose: '.$reason,
                    'Approved: '.$approvedAt,
                    'By: '.$approvedBy,
                ];
                $yy = $boxY + 14;
                foreach ($lines as $ln) {
                    $pdf->SetXY($boxX + 3, $yy);
                    $pdf->Cell($boxW - 6, 3.8, $ln, 0, 0, 'L');
                    $yy += 4;
                }

                $pdf->SetDrawColor(22, 163, 74);
                $pdf->SetLineWidth(0.4);
                $pdf->Line($boxX + 3, $boxY + $boxH - 7, $boxX + $boxW - 3, $boxY + $boxH - 7);
                $pdf->SetXY($boxX + 3, $boxY + $boxH - 6);
                $pdf->SetFont('helvetica', 'I', 6.5);
                $pdf->Cell($boxW - 6, 3, 'Authorized Signatory — Digital Stamp', 0, 0, 'L');
            }
        }

        $stampedPath = 'payslips/stamped/'.$payslip->period.'/'
            .($payslip->employee?->employee_code ?? 'employee')
            .'-stamped-'.$stampedRequest->id.'.pdf';

        Storage::disk('private')->makeDirectory(dirname($stampedPath), 0755, true, true);
        Storage::disk('private')->put($stampedPath, $pdf->Output('', 'S'));

        $stampedRequest->update(['stamped_pdf_path' => $stampedPath]);
    }
}
