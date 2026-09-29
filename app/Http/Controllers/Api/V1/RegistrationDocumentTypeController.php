<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RegistrationDocumentType;
use App\Support\ApiResponse;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegistrationDocumentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = RegistrationDocumentType::forCompany(CurrentCompany::id())
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return ApiResponse::ok(['document_types' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateDocumentType($request);
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Validation failed.', 422, [
                'errors' => $e->errors(),
            ]);
        }

        $validated['company_id'] = CurrentCompany::id();
        $type = RegistrationDocumentType::create($validated);

        AuditLog::record('registration_document_type.created', $type->type, "Document type \"{$type->title}\" created");

        return ApiResponse::ok(['document_type' => $type], 201);
    }

    public function update(Request $request, RegistrationDocumentType $documentType): JsonResponse
    {
        if ((int) $documentType->company_id !== (int) CurrentCompany::id()) {
            return ApiResponse::error('Document type not found.', 404);
        }

        try {
            $validated = $this->validateDocumentType($request, $documentType->id);
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Validation failed.', 422, [
                'errors' => $e->errors(),
            ]);
        }

        $documentType->update($validated);

        AuditLog::record('registration_document_type.updated', $documentType->type, "Document type \"{$documentType->title}\" updated");

        return ApiResponse::ok(['document_type' => $documentType->fresh()]);
    }

    public function destroy(RegistrationDocumentType $documentType): JsonResponse
    {
        if ((int) $documentType->company_id !== (int) CurrentCompany::id()) {
            return ApiResponse::error('Document type not found.', 404);
        }

        $subject = $documentType->type;
        $title = $documentType->title;
        $documentType->delete();

        AuditLog::record('registration_document_type.deleted', $subject, "Document type \"{$title}\" deleted");

        return ApiResponse::ok(['deleted' => true]);
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
}
