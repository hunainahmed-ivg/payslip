<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateStampedPdf;
use App\Models\Payslip;
use App\Models\StampedCopyRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StampedCopyRequestController extends Controller
{
    /**
     * HR dashboard: pending-first request queue.
     */
    public function index(): Response
    {
        $requests = StampedCopyRequest::with(['employee', 'payslip', 'reviewer'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('StampedRequests/Index', [
            'requests' => $requests,
            'pendingCount' => StampedCopyRequest::where('status', 'pending')->count(),
            'success' => session('success'),
        ]);
    }

    /**
     * HR approves → status approved + stamped PDF queued.
     */
    public function approve(Request $request, StampedCopyRequest $stampedCopyRequest): RedirectResponse
    {
        abort_if($stampedCopyRequest->status !== 'pending', 403, 'This request has already been processed.');

        $stampedCopyRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        // 🔐 AUDIT LOG
        AuditLog::record('stamped_request.approved', 'REQ-'.str_pad((string) 
        $stampedCopyRequest->id, 5, '0', STR_PAD_LEFT), 
        $stampedCopyRequest->reason_label.' — '.$stampedCopyRequest->employee?->full_name);

        GenerateStampedPdf::dispatch($stampedCopyRequest);

        return back()->with('success', 'Request approved — stamped PDF queued for generation.');
    }

    /**
     * HR rejects with a mandatory note.
     */
    public function reject(Request $request, StampedCopyRequest $stampedCopyRequest): RedirectResponse
    {
        abort_if($stampedCopyRequest->status !== 'pending', 403, 'This request has already been processed.');

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ]);

        $stampedCopyRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'],
        ]);

        AuditLog::record('stamped_request.rejected', 'REQ-'.str_pad((string) 
        $stampedCopyRequest->id, 5, '0', STR_PAD_LEFT), $stampedCopyRequest->reason_label.' — '.$stampedCopyRequest->employee?->full_name);

        return back()->with('success', 'Request rejected with note.');
    }

    /**
     * Employee portal: request an official stamped copy.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 403, 'No employee profile linked to this account.');

        $validated = $request->validate([
            'payslip_id' => ['required', 'integer', Rule::exists('payslips', 'id')->where('employee_id', $employee->id)],
            'reason' => ['required', Rule::in(['visa_application', 'bank_loan', 'embassy', 'other'])],
            'reason_note' => ['nullable', 'string', 'max:255'],
        ]);

        $payslip = Payslip::findOrFail($validated['payslip_id']);

        abort_if($payslip->status !== 'published', 403, 'Only published payslips can be requested.');

        $hasPending = StampedCopyRequest::where('payslip_id', $payslip->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return back()->with('error', 'A pending request already exists for this payslip.');
        }

        StampedCopyRequest::create([
            'payslip_id' => $payslip->id,
            'employee_id' => $employee->id,
            'reason' => $validated['reason'],
            'reason_note' => $validated['reason_note'] ?? null,
        ]);

        return back()->with('success', 'Stamped copy request submitted — HR will review it shortly.');
    }
}