<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\RegistrationDocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'registration_document_type_id' => RegistrationDocumentType::factory(),
            'side' => 'single',
            'disk' => 'private',
            'path' => 'employee-documents/test/'.fake()->uuid().'.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => null,
        ];
    }
}
