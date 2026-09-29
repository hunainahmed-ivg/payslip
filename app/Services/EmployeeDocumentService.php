<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\RegistrationDocumentType;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmployeeDocumentService
{
    /**
     * @return Collection<int, RegistrationDocumentType>
     */
    public function activeTypes(?int $companyId = null)
    {
        $companyId ??= CurrentCompany::id();

        return RegistrationDocumentType::query()
            ->forCompany($companyId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function requiresProfilePicture(?int $companyId = null): bool
    {
        return $this->activeTypes($companyId)->contains(fn (RegistrationDocumentType $type) => $type->profile_pic_required);
    }

    /**
     * Validate uploaded documents against active configuration.
     *
     * @param  array<string, mixed>  $documents  keyed by document type id => ['front'|'back'|'single' => UploadedFile|null]
     *
     * @throws ValidationException
     */
    public function assertUploadsSatisfyConfig(array $documents, bool $requireProfilePicture, ?UploadedFile $profilePicture, bool $isCreate = true): void
    {
        $types = $this->activeTypes();
        $errors = [];

        if ($requireProfilePicture && $isCreate && ! $profilePicture) {
            $errors['profile_picture'] = ['A profile picture is required.'];
        }

        foreach ($types as $type) {
            $payload = $documents[(string) $type->id] ?? $documents[$type->id] ?? [];

            if ($type->allow_front_back) {
                $front = $payload['front'] ?? null;
                $back = $payload['back'] ?? null;

                if ($type->required && $isCreate) {
                    if (! $front instanceof UploadedFile) {
                        $errors["documents.{$type->id}.front"] = ["{$type->title} (front) is required."];
                    }
                    if (! $back instanceof UploadedFile) {
                        $errors["documents.{$type->id}.back"] = ["{$type->title} (back) is required."];
                    }
                }
            } else {
                $file = $payload['single'] ?? $payload['file'] ?? null;

                if ($type->required && $isCreate && ! $file instanceof UploadedFile) {
                    $errors["documents.{$type->id}.single"] = ["{$type->title} is required."];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Store profile picture and document uploads for an employee.
     *
     * @param  array<string, mixed>  $documents
     */
    public function storeForEmployee(Employee $employee, array $documents, ?UploadedFile $profilePicture = null): void
    {
        $companyId = (int) CurrentCompany::id();
        $basePath = "employee-documents/{$companyId}/{$employee->id}";

        if ($profilePicture instanceof UploadedFile) {
            if ($employee->profile_picture_path) {
                Storage::disk('private')->delete($employee->profile_picture_path);
            }

            $path = $profilePicture->store("{$basePath}/profile", 'private');
            $employee->update(['profile_picture_path' => $path]);

            AuditLog::record(
                'employee.profile_picture_uploaded',
                $employee->employee_code,
                'Profile picture uploaded'
            );
        }

        $types = $this->activeTypes($companyId)->keyBy('id');

        foreach ($documents as $typeId => $sides) {
            $type = $types->get((int) $typeId);
            if (! $type || ! is_array($sides)) {
                continue;
            }

            foreach ($sides as $side => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $normalizedSide = $this->normalizeSide((string) $side, $type);
                $this->storeDocumentFile($employee, $type, $normalizedSide, $file, $basePath);
            }
        }
    }

    public function storeDocumentFile(
        Employee $employee,
        RegistrationDocumentType $type,
        string $side,
        UploadedFile $file,
        ?string $basePath = null
    ): EmployeeDocument {
        $companyId = (int) ($employee->branch?->company_id ?? CurrentCompany::id());
        $basePath ??= "employee-documents/{$companyId}/{$employee->id}";

        $existing = EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('registration_document_type_id', $type->id)
            ->where('side', $side)
            ->first();

        if ($existing) {
            Storage::disk($existing->disk)->delete($existing->path);
            $existing->delete();
        }

        $path = $file->store("{$basePath}/{$type->type}", 'private');

        $document = EmployeeDocument::create([
            'employee_id' => $employee->id,
            'registration_document_type_id' => $type->id,
            'side' => $side,
            'disk' => 'private',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'uploaded_by' => auth()->id(),
        ]);

        AuditLog::record(
            'employee_document.uploaded',
            $employee->employee_code,
            "{$type->title} ({$side}) uploaded"
        );

        return $document;
    }

    private function normalizeSide(string $side, RegistrationDocumentType $type): string
    {
        if ($type->allow_front_back) {
            return in_array($side, ['front', 'back'], true) ? $side : 'front';
        }

        return 'single';
    }
}
