<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\RegistrationDocumentType;
use App\Services\EmployeeDocumentService;
use App\Support\ApiResponse;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeeDocumentController extends Controller
{
    public function __construct(private EmployeeDocumentService $documentService) {}

    public function index(Employee $employee): JsonResponse
    {
        if (! $this->belongsToCurrentCompany($employee)) {
            return ApiResponse::error('Employee not found.', 404);
        }

        $employee->load('documents.documentType');

        return ApiResponse::ok([
            'employee_id' => $employee->id,
            'profile_picture_path' => $employee->profile_picture_path,
            'documents' => $employee->documents,
            'document_types' => $this->documentService->activeTypes(),
            'profile_pic_required' => $this->documentService->requiresProfilePicture(),
        ]);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        if (! $this->belongsToCurrentCompany($employee)) {
            return ApiResponse::error('Employee not found.', 404);
        }

        try {
            $request->validate([
                'registration_document_type_id' => ['required', 'integer', 'exists:registration_document_types,id'],
                'side' => ['nullable', 'in:front,back,single'],
                'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
                'profile_picture' => ['nullable', 'file', 'image', 'max:5120'],
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Validation failed.', 422, [
                'errors' => $e->errors(),
            ]);
        }

        $type = RegistrationDocumentType::query()
            ->forCompany(CurrentCompany::id())
            ->whereKey($request->integer('registration_document_type_id'))
            ->first();

        if (! $type) {
            return ApiResponse::error('Document type not found.', 404);
        }

        $side = $request->input('side', $type->allow_front_back ? 'front' : 'single');
        $document = $this->documentService->storeDocumentFile(
            $employee,
            $type,
            (string) $side,
            $request->file('file')
        );

        if ($request->file('profile_picture')) {
            $this->documentService->storeForEmployee($employee, [], $request->file('profile_picture'));
        }

        return ApiResponse::ok(['document' => $document->load('documentType')], 201);
    }

    private function belongsToCurrentCompany(Employee $employee): bool
    {
        $employee->loadMissing('branch');

        return $employee->branch
            && (int) $employee->branch->company_id === (int) CurrentCompany::id();
    }
}
