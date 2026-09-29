<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RegistrationDocumentType;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationDocumentTypeController extends Controller
{
    public function index(): Response
    {
        $companyId = CurrentCompany::id();

        return Inertia::render('Settings/RegistrationDocuments', [
            'document_types' => RegistrationDocumentType::forCompany($companyId)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(),
            'success' => session('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDocumentType($request);
        $validated['company_id'] = CurrentCompany::id();

        $type = RegistrationDocumentType::create($validated);

        AuditLog::record(
            'registration_document_type.created',
            $type->type,
            "Document type \"{$type->title}\" created"
        );

        return back()->with('success', 'Document type created successfully.');
    }

    public function update(Request $request, RegistrationDocumentType $documentType): RedirectResponse
    {
        $this->assertCompanyDocumentType($documentType);

        $validated = $this->validateDocumentType($request, $documentType->id);
        $documentType->update($validated);

        AuditLog::record(
            'registration_document_type.updated',
            $documentType->type,
            "Document type \"{$documentType->title}\" updated"
        );

        return back()->with('success', 'Document type updated successfully.');
    }

    public function destroy(RegistrationDocumentType $documentType): RedirectResponse
    {
        $this->assertCompanyDocumentType($documentType);

        $subject = $documentType->type;
        $title = $documentType->title;
        $documentType->delete();

        AuditLog::record(
            'registration_document_type.deleted',
            $subject,
            "Document type \"{$title}\" deleted"
        );

        return back()->with('success', 'Document type deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDocumentType(Request $request, ?int $ignoreId = null): array
    {
        $companyId = CurrentCompany::id();

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('registration_document_types', 'type')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($ignoreId),
            ],
            'required' => ['required', 'boolean'],
            'allow_front_back' => ['required', 'boolean'],
            'profile_pic_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    private function assertCompanyDocumentType(RegistrationDocumentType $documentType): void
    {
        abort_unless((int) $documentType->company_id === (int) CurrentCompany::id(), 404);
    }
}
