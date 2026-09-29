<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\SalaryIncrement;
use App\Models\SalaryLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryIncrementTest extends TestCase
{
    use RefreshDatabase;

    private CompanyProfile $company;

    private Branch $branch;

    private User $admin;

    private Employee $employee;

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
            'base_salary' => 100000,
            'employee_code' => 'EMP-3000',
            'full_name' => 'Increment Target',
        ]);
        SalaryLedgerEntry::factory()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'event_type' => 'initial',
            'basic_salary' => 100000,
        ]);
    }

    public function test_admin_can_apply_percent_increment(): void
    {
        $response = $this->actingAs($this->admin)->post(route('salary-increments.store'), [
            'employee_id' => $this->employee->id,
            'increment_type' => 'percent',
            'value' => 10,
            'effective_date' => now()->toDateString(),
            'note' => 'Annual raise',
        ]);

        $response->assertRedirect(route('salary-increments.index', ['employee_id' => $this->employee->id]));

        $this->assertSame('110000.00', $this->employee->fresh()->base_salary);
        $this->assertDatabaseHas('salary_increments', [
            'employee_id' => $this->employee->id,
            'increment_type' => 'percent',
            'new_basic_salary' => 110000,
        ]);
        $this->assertDatabaseHas('salary_ledger_entries', [
            'employee_id' => $this->employee->id,
            'event_type' => 'increment',
            'basic_salary' => 110000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'salary_increment.created',
            'subject' => 'EMP-3000',
        ]);
    }

    public function test_fixed_increment_and_yearly_csv_export(): void
    {
        $this->actingAs($this->admin)->post(route('salary-increments.store'), [
            'employee_id' => $this->employee->id,
            'increment_type' => 'fixed',
            'value' => 5000,
            'effective_date' => now()->toDateString(),
            'note' => 'Adjustment',
        ])->assertRedirect();

        $this->assertSame('105000.00', $this->employee->fresh()->base_salary);

        $csv = $this->actingAs($this->admin)->get(route('salary-increments.export.csv', [
            'year' => now()->year,
        ]));

        $csv->assertOk();
        $csv->assertHeader('content-disposition');
        $this->assertStringContainsString('EMP-3000', $csv->streamedContent());
    }

    public function test_employee_role_cannot_access_increments(): void
    {
        $employeeUser = User::factory()->employee()->create([
            'company_id' => $this->company->id,
        ]);

        $this->actingAs($employeeUser)
            ->get(route('salary-increments.index'))
            ->assertForbidden();
    }

    public function test_increments_are_append_only_no_update_route(): void
    {
        $increment = SalaryIncrement::factory()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'previous_basic_salary' => 100000,
            'new_basic_salary' => 110000,
            'increment_type' => 'percent',
            'value' => 10,
        ]);

        $this->actingAs($this->admin)
            ->put('/salary-increments/'.$increment->id, [
                'value' => 20,
            ])
            ->assertNotFound();
    }
}
