<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payslip;
use App\Models\StampedCopyRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayslipAccessController extends Controller
{
    /**
     * Secure inline view for payslip PDF.
     */
    public function viewPayslip(Payslip $payslip): StreamedResponse
    {
        $this->authorizePayslip($payslip);

        AuditLog::record(
            'payslip.viewed',
            'PSL-'.str_pad((string) $payslip->id, 6, '0', STR_PAD_LEFT),
            $payslip->period.' · '.($payslip->employee?->employee_code ?? '')
        );

        $filename = $this->payslipFilename($payslip);

        return response()->stream(
            function () use ($payslip) {
                echo Storage::disk('private')->get($payslip->pdf_path);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]
        );
    }

    /**
     * Secure download for payslip PDF.
     */
    public function downloadPayslip(Payslip $payslip): StreamedResponse
    {
        $this->authorizePayslip($payslip);

        AuditLog::record(
            'payslip.downloaded',
            'PSL-'.str_pad((string) $payslip->id, 6, '0', STR_PAD_LEFT),
            $payslip->period.' · '.($payslip->employee?->employee_code ?? '')
        );

        $filename = $this->payslipFilename($payslip);

        return response()->streamDownload(
            function () use ($payslip) {
                echo Storage::disk('private')->get($payslip->pdf_path);
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]
        );
    }

    /**
     * Secure inline view for stamped copy PDF.
     */
    public function viewStampedCopy(StampedCopyRequest $stampedRequest): StreamedResponse
    {
        $this->authorizeStampedCopy($stampedRequest);

        AuditLog::record(
            'stamped_request.viewed',
            'REQ-'.str_pad((string) $stampedRequest->id, 5, '0', STR_PAD_LEFT),
            $stampedRequest->reason_label.' · '.($stampedRequest->employee?->full_name ?? '')
        );

        $filename = $this->stampedFilename($stampedRequest);

        return response()->stream(
            function () use ($stampedRequest) {
                echo Storage::disk('private')->get($stampedRequest->stamped_pdf_path);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]
        );
    }

    /**
     * Secure download for stamped copy PDF.
     */
    public function downloadStampedCopy(StampedCopyRequest $stampedRequest): StreamedResponse
    {
        $this->authorizeStampedCopy($stampedRequest);

        AuditLog::record(
            'stamped_request.downloaded',
            'REQ-'.str_pad((string) $stampedRequest->id, 5, '0', STR_PAD_LEFT),
            $stampedRequest->reason_label.' · '.($stampedRequest->employee?->full_name ?? '')
        );

        $filename = $this->stampedFilename($stampedRequest);

        return response()->streamDownload(
            function () use ($stampedRequest) {
                echo Storage::disk('private')->get($stampedRequest->stamped_pdf_path);
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]
        );
    }

    private function authorizePayslip(Payslip $payslip): void
    {
        abort_unless($payslip->status === 'published', 404);
        abort_unless($payslip->pdf_path, 404);
        abort_unless(Storage::disk('private')->exists($payslip->pdf_path), 404);

        $user = auth()->user();

        abort_unless($user, 401);
        abort_unless($this->canAccessEmployee($user, $payslip->employee_id), 403);
    }

    private function authorizeStampedCopy(StampedCopyRequest $stampedRequest): void
    {
        $stampedRequest->loadMissing('payslip', 'employee');

        abort_unless($stampedRequest->status === 'approved', 404);
        abort_unless($stampedRequest->stamped_pdf_path, 404);
        abort_unless(Storage::disk('private')->exists($stampedRequest->stamped_pdf_path), 404);

        $user = auth()->user();

        abort_unless($user, 401);

        $employeeId = $stampedRequest->employee_id
            ?? $stampedRequest->payslip?->employee_id;

        abort_unless($employeeId, 404);
        abort_unless($this->canAccessEmployee($user, (int) $employeeId), 403);
    }

    private function canAccessEmployee($user, int $employeeId): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isHrOrAdmin($user)) {
            return true;
        }

        return $this->ownsEmployee($user, $employeeId);
    }

    private function ownsEmployee($user, int $employeeId): bool
    {
        $userEmployeeId = $user->getAttribute('employee_id');

        if ($userEmployeeId && (int) $userEmployeeId === $employeeId) {
            return true;
        }

        if (method_exists($user, 'employee')) {
            $employee = $user->employee;

            if ($employee && (int) $employee->id === $employeeId) {
                return true;
            }
        }

        return false;
    }

    private function isHrOrAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole(['hr', 'admin', 'super-admin', 'administrator']);
        }

        $role = $user->getAttribute('role')
            ?? $user->getAttribute('user_role')
            ?? null;

        if (is_string($role)) {
            return in_array(strtolower($role), [
                'hr',
                'admin',
                'super-admin',
                'administrator',
            ], true);
        }

        if ((bool) $user->getAttribute('is_admin')) {
            return true;
        }

        return false;
    }

    private function payslipFilename(Payslip $payslip): string
    {
        $employeeCode = $payslip->employee?->employee_code ?? 'employee';
        $period = $payslip->period ?? 'payslip';

        $filename = $employeeCode.'-'.$period.'.pdf';

        return preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    }

    private function stampedFilename(StampedCopyRequest $stampedRequest): string
    {
        $employeeCode = $stampedRequest->employee?->employee_code
            ?? $stampedRequest->payslip?->employee?->employee_code
            ?? 'employee';

        $period = $stampedRequest->payslip?->period ?? 'stamped';
        $reference = 'REQ-'.str_pad((string) $stampedRequest->id, 5, '0', STR_PAD_LEFT);

        $filename = $employeeCode.'-'.$period.'-stamped-'.$reference.'.pdf';

        return preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    }
}