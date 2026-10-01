<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\RegistrationDocumentType;
use App\Models\SalaryLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeDocumentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private CompanyProfile $company;

    private Branch $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->company = CompanyProfile::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_employee_create_requires_configured_documents_and_profile_picture(): void
    {
        RegistrationDocumentType::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'CNIC',
            'type' => 'cnic',
            'required' => true,
            'allow_front_back' => true,
            'profile_pic_required' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('employees.store'), [
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'department' => 'IT',
            'designation' => 'Dev',
            'branch_id' => $this->branch->id,
            'currency_code' => 'PKR',
            'base_salary' => 50000,
            'joined_on' => now()->toDateString(),
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('employees', ['email' => 'test@example.com']);
    }

    public function test_employee_create_stores_documents_profile_picture_and_initial_ledger(): void
    {
        $type = RegistrationDocumentType::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'CNIC',
            'type' => 'cnic',
            'required' => true,
            'allow_front_back' => true,
            'profile_pic_required' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('employees.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'department' => 'HR',
            'designation' => 'Officer',
            'branch_id' => $this->branch->id,
            'currency_code' => 'PKR',
            'base_salary' => 60000,
            'joined_on' => now()->toDateString(),
            'is_active' => true,
            'profile_picture' => UploadedFile::fake()->image('photo.jpg'),
            'documents' => [
                $type->id => [
                    'front' => UploadedFile::fake()->create('cnic-front.pdf', 100, 'application/pdf'),
                    'back' => UploadedFile::fake()->create('cnic-back.pdf', 100, 'application/pdf'),
                ],
            ],
        ]);

        $response->assertRedirect(route('employees.index'));

        $employee = Employee::where('email', 'jane@example.com')->first();
        $this->assertNotNull($employee);
        $this->assertMatchesRegularExpression('/^EMP-\d{4,}$/', $employee->employee_code);
        $this->assertNotNull($employee->profile_picture_path);
        $this->assertTrue(Storage::disk('private')->exists($employee->profile_picture_path));
        $this->assertSame(2, $employee->documents()->count());
        $this->assertDatabaseHas('salary_ledger_entries', [
            'employee_id' => $employee->id,
            'event_type' => 'initial',
            'basic_salary' => 60000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.created',
            'subject' => $employee->employee_code,
        ]);
    }

    public function test_employee_code_is_auto_generated_and_sequential(): void
    {
        Employee::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-0042',
        ]);

        $this->actingAs($this->admin)->post(route('employees.store'), [
            'full_name' => 'Auto Code User',
            'email' => 'auto.code@example.com',
            'department' => 'IT',
            'designation' => 'Dev',
            'branch_id' => $this->branch->id,
            'currency_code' => 'PKR',
            'base_salary' => 45000,
            'joined_on' => now()->toDateString(),
            'is_active' => true,
        ])->assertRedirect(route('employees.index'));

        $employee = Employee::where('email', 'auto.code@example.com')->first();
        $this->assertNotNull($employee);
        $this->assertSame('EMP-0043', $employee->employee_code);
    }

    public function test_employee_update_cannot_change_base_salary(): void
    {
        $employee = Employee::factory()->create([
            'branch_id' => $this->branch->id,
            'base_salary' => 50000,
            'employee_code' => 'EMP-2000',
        ]);

        SalaryLedgerEntry::factory()->create([
            'employee_id' => $employee->id,
            'company_id' => $this->company->id,
            'event_type' => 'initial',
            'basic_salary' => 50000,
        ]);

        $this->actingAs($this->admin)->put(route('employees.update', $employee), [
            'employee_code' => 'EMP-HACKED',
            'full_name' => 'Updated Name',
            'email' => $employee->email,
            'department' => 'Ops',
            'designation' => 'Lead',
            'branch_id' => $this->branch->id,
            'currency_code' => 'PKR',
            'base_salary' => 999999,
            'joined_on' => now()->toDateString(),
            'is_active' => true,
        ])->assertRedirect();

        $fresh = $employee->fresh();
        $this->assertSame('50000.00', $fresh->base_salary);
        $this->assertSame('Updated Name', $fresh->full_name);
        $this->assertSame('EMP-2000', $fresh->employee_code);
    }
}
