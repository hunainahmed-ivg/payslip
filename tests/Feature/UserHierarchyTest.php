<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_platform_admin_resolves_as_super_admin(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => null,
        ]);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertFalse($user->isCompanyAdmin());
        $this->assertTrue($user->hasPermission(Permission::CompaniesManage));
        $this->assertSame('Super Admin', $user->roleLabel());
    }

    public function test_legacy_tenant_admin_resolves_as_company_admin(): void
    {
        $company = CompanyProfile::factory()->create();
        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue($user->isCompanyAdmin());
        $this->assertFalse($user->hasPermission(Permission::CompaniesManage));
        $this->assertSame('Company Admin', $user->roleLabel());
    }

    public function test_company_admin_cannot_access_another_company(): void
    {
        $companyA = CompanyProfile::factory()->create();
        $companyB = CompanyProfile::factory()->create();
        $admin = User::factory()->companyAdmin($companyA->id)->create();

        $this->assertTrue($admin->canAccessCompany($companyA->id));
        $this->assertFalse($admin->canAccessCompany($companyB->id));
    }

    public function test_super_admin_can_create_company_admin_for_a_company(): void
    {
        $super = User::factory()->superAdmin()->create();
        $company = CompanyProfile::factory()->create();

        $this->actingAs($super)
            ->post(route('settings.users.store'), [
                'name' => 'Acme Admin',
                'email' => 'acme.admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::CompanyAdmin->value,
                'company_id' => $company->id,
                'use_custom_permissions' => false,
                'permissions' => [],
            ])
            ->assertRedirect();

        $created = User::where('email', 'acme.admin@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame(UserRole::CompanyAdmin->value, $created->role);
        $this->assertSame($company->id, (int) $created->company_id);
        $this->assertFalse($created->hasPermission(Permission::CompaniesManage));
    }

    public function test_company_admin_can_only_create_employees_in_own_company(): void
    {
        $companyA = CompanyProfile::factory()->create();
        $companyB = CompanyProfile::factory()->create();
        $admin = User::factory()->companyAdmin($companyA->id)->create();

        $this->actingAs($admin)
            ->from(route('settings.users.index'))
            ->post(route('settings.users.store'), [
                'name' => 'Other Admin Attempt',
                'email' => 'other.admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::CompanyAdmin->value,
                'company_id' => $companyB->id,
                'use_custom_permissions' => false,
                'permissions' => [],
            ])
            ->assertRedirect(route('settings.users.index'))
            ->assertSessionHasErrors('role');

        $this->assertNull(User::where('email', 'other.admin@example.com')->first());

        $this->actingAs($admin)
            ->post(route('settings.users.store'), [
                'name' => 'Staff Member',
                'email' => 'staff@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::Employee->value,
                'company_id' => $companyB->id,
                'use_custom_permissions' => false,
                'permissions' => [],
            ])
            ->assertRedirect();

        $created = User::where('email', 'staff@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame(UserRole::Employee->value, $created->role);
        $this->assertSame($companyA->id, (int) $created->company_id);
    }

    public function test_company_admin_cannot_open_companies_page(): void
    {
        $company = CompanyProfile::factory()->create();
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('companies.index'))
            ->assertForbidden();
    }

    public function test_assignable_roles_match_hierarchy(): void
    {
        $super = User::factory()->superAdmin()->create();
        $company = CompanyProfile::factory()->create();
        $companyAdmin = User::factory()->companyAdmin($company->id)->create();
        $employee = User::factory()->employee()->create(['company_id' => $company->id]);

        $this->assertSame(
            [UserRole::CompanyAdmin->value, UserRole::Employee->value, UserRole::SuperAdmin->value],
            array_column(RolePermissions::assignableRolesFor($super), 'value'),
        );
        $this->assertSame(
            [UserRole::Employee->value],
            array_column(RolePermissions::assignableRolesFor($companyAdmin), 'value'),
        );
        $this->assertSame([], RolePermissions::assignableRolesFor($employee));
    }

    public function test_employee_cannot_open_users_or_employees_pages(): void
    {
        $company = CompanyProfile::factory()->create();
        $employee = User::factory()->employee()->create(['company_id' => $company->id]);

        $this->actingAs($employee)
            ->get(route('settings.users.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('employees.index'))
            ->assertForbidden();
    }

    public function test_employee_cannot_elevate_via_custom_permissions(): void
    {
        $company = CompanyProfile::factory()->create();
        $employee = User::factory()->employee()->create([
            'company_id' => $company->id,
            'assigned_permissions' => [
                Permission::DashboardView->value,
                Permission::PortalPayslips->value,
                Permission::UsersManage->value,
                Permission::EmployeesManage->value,
            ],
        ]);

        $this->assertFalse($employee->hasPermission(Permission::UsersManage));
        $this->assertFalse($employee->hasPermission(Permission::EmployeesManage));
        $this->assertTrue($employee->hasPermission(Permission::DashboardView));

        $this->actingAs($employee)
            ->get(route('settings.users.index'))
            ->assertForbidden();
    }

    public function test_company_admin_sees_company_users_and_can_edit_employees_only(): void
    {
        $company = CompanyProfile::factory()->create();
        $otherCompany = CompanyProfile::factory()->create();

        $admin = User::factory()->companyAdmin($company->id)->create(['name' => 'Acme Admin']);
        $peerAdmin = User::factory()->companyAdmin($company->id)->create(['name' => 'Peer Admin']);
        $staff = User::factory()->employee()->create([
            'company_id' => $company->id,
            'name' => 'Staff User',
        ]);
        $outsider = User::factory()->employee()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Co Staff',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('settings.users.index'))
            ->assertOk();

        $users = collect($response->original->getData()['page']['props']['users']);
        $ids = $users->pluck('id')->all();

        $this->assertContains($admin->id, $ids);
        $this->assertContains($peerAdmin->id, $ids);
        $this->assertContains($staff->id, $ids);
        $this->assertNotContains($outsider->id, $ids);

        $staffRow = $users->firstWhere('id', $staff->id);
        $peerRow = $users->firstWhere('id', $peerAdmin->id);

        $this->assertTrue($staffRow['can_manage']);
        $this->assertFalse($peerRow['can_manage']);

        $this->actingAs($admin)
            ->put(route('settings.users.update', $peerAdmin), [
                'name' => $peerAdmin->name,
                'email' => $peerAdmin->email,
                'password' => null,
                'password_confirmation' => null,
                'role' => UserRole::Employee->value,
                'company_id' => $company->id,
                'use_custom_permissions' => false,
                'permissions' => [],
            ])
            ->assertForbidden();
    }

    public function test_super_admin_sees_all_users(): void
    {
        $companyA = CompanyProfile::factory()->create();
        $companyB = CompanyProfile::factory()->create();
        $super = User::factory()->superAdmin()->create();
        $adminA = User::factory()->companyAdmin($companyA->id)->create();
        $staffB = User::factory()->employee()->create(['company_id' => $companyB->id]);

        $response = $this->actingAs($super)
            ->get(route('settings.users.index'))
            ->assertOk();

        $ids = collect($response->original->getData()['page']['props']['users'])->pluck('id')->all();

        $this->assertContains($super->id, $ids);
        $this->assertContains($adminA->id, $ids);
        $this->assertContains($staffB->id, $ids);
    }

    public function test_cannot_assign_admin_pages_to_employee_role(): void
    {
        $company = CompanyProfile::factory()->create();
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->from(route('settings.users.index'))
            ->post(route('settings.users.store'), [
                'name' => 'Elevated Attempt',
                'email' => 'elevated@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::Employee->value,
                'company_id' => $company->id,
                'use_custom_permissions' => true,
                'permissions' => [
                    Permission::DashboardView->value,
                    Permission::UsersManage->value,
                    Permission::EmployeesManage->value,
                ],
            ])
            ->assertRedirect(route('settings.users.index'))
            ->assertSessionHasErrors('permissions');

        $this->assertNull(User::where('email', 'elevated@example.com')->first());
    }

    public function test_company_admin_can_grant_visual_identity_to_employee(): void
    {
        $company = CompanyProfile::factory()->create();
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->post(route('settings.users.store'), [
                'name' => 'Brand Editor',
                'email' => 'brand.editor@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::Employee->value,
                'company_id' => $company->id,
                'use_custom_permissions' => true,
                'permissions' => [
                    Permission::DashboardView->value,
                    Permission::PortalPayslips->value,
                    Permission::SettingsCompanyProfile->value,
                    Permission::SettingsVisualIdentity->value,
                ],
            ])
            ->assertRedirect();

        $created = User::where('email', 'brand.editor@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasPermission(Permission::SettingsCompanyProfile));
        $this->assertTrue($created->hasPermission(Permission::SettingsVisualIdentity));
        $this->assertFalse($created->hasPermission(Permission::UsersManage));

        $this->actingAs($created)
            ->get(route('settings.company-profile'))
            ->assertOk();

        $this->actingAs($created)
            ->get(route('settings.visual-identity'))
            ->assertOk();
    }

    public function test_employee_without_visual_identity_cannot_edit_company_profile(): void
    {
        $company = CompanyProfile::factory()->create();
        $employee = User::factory()->employee()->create([
            'company_id' => $company->id,
            'assigned_permissions' => [
                Permission::DashboardView->value,
                Permission::PortalPayslips->value,
                Permission::SettingsCompanyProfile->value,
            ],
        ]);

        $this->assertFalse($employee->hasPermission(Permission::SettingsVisualIdentity));

        $this->actingAs($employee)
            ->get(route('settings.company-profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/CompanyProfile'));

        $this->actingAs($employee)
            ->get(route('settings.visual-identity'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('settings.visual-identity.update'), [
                'company_name' => 'Hacked Co',
                'template_type' => 'modern',
                'primary_color' => '#000000',
                'accent_color' => '#111111',
                'font_family' => 'Inter',
                'page_margin' => '18mm',
            ])
            ->assertForbidden();
    }
}
