<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\RegistrationDocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDocumentTypeTest extends TestCase
{
    use RefreshDatabase;

    private CompanyProfile $company;

    private User $admin;

    private User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = CompanyProfile::factory()->create();
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        $this->employeeUser = User::factory()->employee()->create([
            'company_id' => $this->company->id,
        ]);
    }

    public function test_admin_can_create_document_type_and_audit_is_recorded(): void
    {
        $response = $this->actingAs($this->admin)->post(route('settings.registration-documents.store'), [
            'title' => 'CNIC',
            'type' => 'cnic',
            'required' => true,
            'allow_front_back' => true,
            'profile_pic_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('registration_document_types', [
            'company_id' => $this->company->id,
            'type' => 'cnic',
            'required' => true,
            'allow_front_back' => true,
            'profile_pic_required' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'registration_document_type.created',
            'subject' => 'cnic',
        ]);
    }

    public function test_employee_cannot_manage_document_types(): void
    {
        $response = $this->actingAs($this->employeeUser)->post(route('settings.registration-documents.store'), [
            'title' => 'Passport',
            'type' => 'passport',
            'required' => true,
            'allow_front_back' => false,
            'profile_pic_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_and_delete_document_type(): void
    {
        $type = RegistrationDocumentType::factory()->create([
            'company_id' => $this->company->id,
            'type' => 'degree',
            'title' => 'Degree',
        ]);

        $this->actingAs($this->admin)->put(route('settings.registration-documents.update', $type), [
            'title' => 'Degree Certificate',
            'type' => 'degree',
            'required' => false,
            'allow_front_back' => false,
            'profile_pic_required' => false,
            'is_active' => false,
            'sort_order' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('registration_document_types', [
            'id' => $type->id,
            'title' => 'Degree Certificate',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'registration_document_type.updated',
            'subject' => 'degree',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('settings.registration-documents.destroy', $type))
            ->assertRedirect();

        $this->assertDatabaseMissing('registration_document_types', ['id' => $type->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'registration_document_type.deleted',
            'subject' => 'degree',
        ]);
    }
}
