<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\RegistrationDocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryIncrementApiTest extends TestCase
{
    use RefreshDatabase;

    private CompanyProfile $company;

    private Branch $branch;

    private User $admin;

    private Employee $employee;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = CompanyProfile::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        $this->employee = Employee::factory()->create([
            'branch_id' => $this->branch->id,
            'base_salary' => 80000,
            'employee_code' => 'EMP-API-1',
            'full_name' => 'API Employee',
        ]);

        $newToken = $this->admin->createToken('test', ['*']);
        $newToken->accessToken->forceFill(['company_id' => $this->company->id])->save();
        $this->token = $newToken->plainTextToken;
    }

    public function test_preview_returns_ok_envelope(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/salary-increments/preview', [
            'employee_id' => $this->employee->id,
            'increment_type' => 'percent',
            'value' => 12.5,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('error', null)
            ->assertJsonPath('data.current_basic_salary', 80000)
            ->assertJsonPath('data.new_basic_salary', 90000);
    }

    public function test_store_validation_error_uses_envelope(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/salary-increments', [
            'employee_id' => $this->employee->id,
            'increment_type' => 'percent',
            'value' => 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['ok', 'data', 'error']);
        $this->assertNotNull($response->json('error'));
    }

    public function test_document_type_api_create_returns_envelope(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/registration-document-types', [
            'title' => 'Passport',
            'type' => 'passport',
            'required' => true,
            'allow_front_back' => false,
            'profile_pic_required' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.document_type.type', 'passport');

        $this->assertDatabaseHas('registration_document_types', [
            'company_id' => $this->company->id,
            'type' => 'passport',
        ]);
    }

    public function test_document_type_api_validation_error_envelope(): void
    {
        RegistrationDocumentType::factory()->create([
            'company_id' => $this->company->id,
            'type' => 'cnic',
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v1/registration-document-types', [
            'title' => 'CNIC',
            'type' => 'cnic',
            'required' => true,
            'allow_front_back' => true,
            'profile_pic_required' => false,
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);
    }
}
