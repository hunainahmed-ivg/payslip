<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\SalaryIncrement;
use App\Models\SalaryLedgerEntry;
use App\Support\CurrentCompany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryIncrementService
{
    /**
     * @return array{current_basic_salary: float, new_basic_salary: float, increment_type: string, value: float}
     */
    public function preview(Employee $employee, string $incrementType, float $value): array
    {
        $current = (float) $employee->base_salary;
        $new = $this->calculateNewSalary($current, $incrementType, $value);

        return [
            'current_basic_salary' => round($current, 2),
            'new_basic_salary' => $new,
            'increment_type' => $incrementType,
            'value' => $value,
        ];
    }

    public function calculateNewSalary(float $current, string $incrementType, float $value): float
    {
        if ($value < 0) {
            throw ValidationException::withMessages([
                'value' => ['Increment value must be zero or greater.'],
            ]);
        }

        $new = match ($incrementType) {
            'percent' => $current * (1 + ($value / 100)),
            'fixed' => $current + $value,
            default => throw ValidationException::withMessages([
                'increment_type' => ['Increment type must be percent or fixed.'],
            ]),
        };

        return round($new, 2);
    }

    /**
     * @param  array{increment_type: string, value: float|int|string, effective_date: string, note?: string|null}  $data
     */
    public function apply(Employee $employee, array $data, ?int $createdBy = null): SalaryIncrement
    {
        $companyId = (int) ($employee->branch?->company_id ?? CurrentCompany::id());
        $previous = (float) $employee->base_salary;
        $incrementType = $data['increment_type'];
        $value = (float) $data['value'];
        $newSalary = $this->calculateNewSalary($previous, $incrementType, $value);

        return DB::transaction(function () use ($employee, $data, $createdBy, $companyId, $previous, $incrementType, $value, $newSalary) {
            $increment = SalaryIncrement::create([
                'employee_id' => $employee->id,
                'company_id' => $companyId,
                'previous_basic_salary' => $previous,
                'increment_type' => $incrementType,
                'value' => $value,
                'new_basic_salary' => $newSalary,
                'effective_date' => $data['effective_date'],
                'note' => $data['note'] ?? null,
                'created_by' => $createdBy ?? auth()->id(),
            ]);

            SalaryLedgerEntry::create([
                'employee_id' => $employee->id,
                'company_id' => $companyId,
                'event_type' => 'increment',
                'salary_increment_id' => $increment->id,
                'previous_basic_salary' => $previous,
                'basic_salary' => $newSalary,
                'effective_date' => $data['effective_date'],
                'note' => $data['note'] ?? null,
                'created_by' => $createdBy ?? auth()->id(),
            ]);

            $employee->update(['base_salary' => $newSalary]);

            AuditLog::record(
                'salary_increment.created',
                $employee->employee_code,
                "Salary {$previous} → {$newSalary} ({$incrementType} {$value})"
            );

            return $increment;
        });
    }

    public function recordInitialSalary(Employee $employee, ?int $createdBy = null): SalaryLedgerEntry
    {
        $companyId = (int) ($employee->branch?->company_id ?? CurrentCompany::id());

        $entry = SalaryLedgerEntry::create([
            'employee_id' => $employee->id,
            'company_id' => $companyId,
            'event_type' => 'initial',
            'salary_increment_id' => null,
            'previous_basic_salary' => null,
            'basic_salary' => $employee->base_salary,
            'effective_date' => $employee->joined_on?->toDateString() ?? now()->toDateString(),
            'note' => 'Initial basic salary',
            'created_by' => $createdBy ?? auth()->id(),
        ]);

        AuditLog::record(
            'salary_ledger.initial',
            $employee->employee_code,
            "Initial salary recorded: {$employee->base_salary}"
        );

        return $entry;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function yearlyReport(?int $companyId = null, ?int $year = null): Collection
    {
        $companyId ??= CurrentCompany::id();
        $year ??= (int) now()->year;

        return SalaryIncrement::query()
            ->with('employee:id,employee_code,full_name')
            ->where('company_id', $companyId)
            ->whereYear('effective_date', $year)
            ->orderBy('effective_date')
            ->get()
            ->map(fn (SalaryIncrement $increment) => [
                'id' => $increment->id,
                'employee_id' => $increment->employee_id,
                'employee_code' => $increment->employee?->employee_code,
                'full_name' => $increment->employee?->full_name,
                'previous_basic_salary' => $increment->previous_basic_salary,
                'increment_type' => $increment->increment_type,
                'value' => $increment->value,
                'new_basic_salary' => $increment->new_basic_salary,
                'effective_date' => $increment->effective_date?->toDateString(),
                'note' => $increment->note,
            ]);
    }
}
