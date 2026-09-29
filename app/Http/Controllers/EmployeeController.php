<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\SalaryComponent;
use App\Services\EmployeeDocumentService;
use App\Services\SalaryIncrementService;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeDocumentService $documentService,
        private SalaryIncrementService $salaryIncrementService,
    ) {}

    public function index(): Response
    {
        $companyId = CurrentCompany::id();

        return Inertia::render('Employees/Index', [
            'employees' => Employee::with(['branch', 'documents.documentType'])
                ->whereHas('branch', fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('full_name')
                ->get(),
            'branches' => Branch::where('company_id', $companyId)->orderBy('name')->get(),
            'document_types' => $this->documentService->activeTypes($companyId),
            'profile_pic_required' => $this->documentService->requiresProfilePicture($companyId),
            'success' => session('success'),
        ]);
    }

    public function show(Employee $employee): Response
    {
        $this->assertCompanyEmployee($employee);

        $employee->load('branch', 'salaryComponents', 'documents.documentType');

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
            'masterComponents' => SalaryComponent::active()
                ->forCompany(CurrentCompany::id())
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'success' => session('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEmployee($request);
        $documents = $request->input('documents', []) ?: [];
        $files = $request->file('documents', []) ?: [];
        $mergedDocuments = $this->mergeDocumentPayload($documents, $files);
        $profilePicture = $request->file('profile_picture');

        $this->documentService->assertUploadsSatisfyConfig(
            $mergedDocuments,
            $this->documentService->requiresProfilePicture(),
            $profilePicture,
            true
        );

        $employee = DB::transaction(function () use ($validated, $mergedDocuments, $profilePicture) {
            $employee = Employee::create($validated);
            $this->salaryIncrementService->recordInitialSalary($employee);
            $this->documentService->storeForEmployee($employee, $mergedDocuments, $profilePicture);

            return $employee;
        });

        AuditLog::record('employee.created', $employee->employee_code, "Employee {$employee->full_name} created");

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->assertCompanyEmployee($employee);

        $validated = $this->validateEmployee($request, $employee->id, false);
        unset($validated['base_salary']);

        $documents = $request->input('documents', []) ?: [];
        $files = $request->file('documents', []) ?: [];
        $mergedDocuments = $this->mergeDocumentPayload($documents, $files);
        $profilePicture = $request->file('profile_picture');

        $this->documentService->assertUploadsSatisfyConfig(
            $mergedDocuments,
            false,
            $profilePicture,
            false
        );

        DB::transaction(function () use ($employee, $validated, $mergedDocuments, $profilePicture) {
            $employee->update($validated);
            $this->documentService->storeForEmployee($employee, $mergedDocuments, $profilePicture);
        });

        AuditLog::record('employee.updated', $employee->employee_code, "Employee {$employee->full_name} updated");

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated successfully');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->assertCompanyEmployee($employee);

        $code = $employee->employee_code;
        $name = $employee->full_name;
        $employee->delete();

        AuditLog::record('employee.deleted', $code, "Employee {$name} deleted");

        return redirect()->route('employees.index')->with('success', 'Employee deleted successfully');
    }

    public function downloadDocument(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        $this->assertCompanyEmployee($employee);
        abort_unless((int) $document->employee_id === (int) $employee->id, 404);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_filename ?? basename($document->path)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request, ?int $ignoreId = null, bool $requireSalary = true): array
    {
        $companyId = CurrentCompany::id();

        $rules = [
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_code')->ignore($ignoreId)],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($ignoreId)],
            'department' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'currency_code' => ['required', 'string', 'size:3'],
            'joined_on' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'profile_picture' => ['nullable', 'file', 'image', 'max:5120'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'array'],
            'documents.*.single' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'documents.*.front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'documents.*.back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];

        if ($requireSalary) {
            $rules['base_salary'] = ['required', 'numeric', 'min:0', 'max:999999999'];
        }

        $validated = $request->validate($rules);

        return collect($validated)->only([
            'employee_code',
            'full_name',
            'email',
            'department',
            'designation',
            'branch_id',
            'currency_code',
            'base_salary',
            'joined_on',
            'is_active',
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $documents
     * @param  array<string, mixed>  $files
     * @return array<string, array<string, mixed>>
     */
    private function mergeDocumentPayload(array $documents, array $files): array
    {
        $merged = [];

        foreach (array_unique(array_merge(array_keys($documents), array_keys($files))) as $typeId) {
            $merged[(string) $typeId] = array_merge(
                is_array($documents[$typeId] ?? null) ? $documents[$typeId] : [],
                is_array($files[$typeId] ?? null) ? $files[$typeId] : []
            );
        }

        return $merged;
    }

    private function assertCompanyEmployee(Employee $employee): void
    {
        $employee->loadMissing('branch');
        abort_unless(
            $employee->branch && (int) $employee->branch->company_id === (int) CurrentCompany::id(),
            404
        );
    }
}
