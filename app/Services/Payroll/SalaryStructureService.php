<?php

namespace App\Services\Payroll;

use App\Enums\PayrollRunStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\User;
use App\Services\System\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Effective-dated salary structures (FR-PRL-02). A structure is never edited: a salary change is a new structure
 * from its effective date, which closes the previous one the day before. Periods already approved are never
 * changed; corrections for them go in the next run as adjustments (docs/07 D6).
 */
class SalaryStructureService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{basic_salary: string, effective_from: string, notes?: string|null, components?: array<int|string, string|null>}  $data
     */
    public function create(Employee $employee, array $data, User $actor): SalaryStructure
    {
        $from = Carbon::parse($data['effective_from'])->startOfDay();
        $basic = Money::of((string) $data['basic_salary']);

        if (! $basic->isPositive()) {
            throw new BusinessRuleException('The basic salary must be greater than zero.');
        }

        if ($employee->hire_date !== null && $from->lessThan($employee->hire_date)) {
            throw new BusinessRuleException('A salary cannot start before the hire date ('.$employee->hire_date->toDateString().').');
        }

        $components = $this->components($data['components'] ?? []);

        return DB::transaction(function () use ($employee, $data, $from, $basic, $components, $actor): SalaryStructure {
            $latest = SalaryStructure::query()->where('employee_id', $employee->id)->lockForUpdate()->orderByDesc('effective_from')->first();

            if ($latest !== null && $from->lessThanOrEqualTo($latest->effective_from)) {
                throw new BusinessRuleException('The new salary must start after the current one ('.$latest->effective_from->toDateString().').');
            }

            $lastPaid = $this->lastReleasedRunFor($employee);

            if ($lastPaid !== null && $from->lessThanOrEqualTo($lastPaid->periodEnd())) {
                throw new BusinessRuleException("Payroll for {$lastPaid->periodLabel()} is already approved. Start the new salary after {$lastPaid->periodEnd()->toDateString()} and pay any arrears as an adjustment in the next run.");
            }

            if ($latest !== null && ($latest->effective_to === null || $latest->effective_to->greaterThanOrEqualTo($from))) {
                $latest->forceFill(['effective_to' => $from->copy()->subDay()->toDateString()])->save();
            }

            $structure = new SalaryStructure;
            $structure->forceFill([
                'employee_id' => $employee->id,
                'basic_salary' => $basic->toDecimal(),
                'effective_from' => $from->toDateString(),
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'created_by' => $actor->id,
            ])->save();

            foreach ($components as $componentId => $value) {
                $line = new SalaryStructureComponent;
                $line->forceFill(['salary_structure_id' => $structure->id, 'pay_component_id' => $componentId, 'value' => $value])->save();
            }

            $this->auditLogger->log('salary_structure_created', $employee, "Salary structure effective {$from->toDateString()} recorded for {$employee->fullName()}");

            return $structure->load('components.payComponent');
        });
    }

    /**
     * Remove the latest structure while no locked payroll has used it, reopening the previous one.
     */
    public function delete(SalaryStructure $structure): void
    {
        DB::transaction(function () use ($structure): void {
            $latest = SalaryStructure::query()->where('employee_id', $structure->employee_id)->lockForUpdate()->orderByDesc('effective_from')->first();

            if ($latest?->id !== $structure->id) {
                throw new BusinessRuleException('Only the latest salary structure can be removed.');
            }

            $usedByLockedRun = PayrollRun::query()
                ->whereIn('status', [PayrollRunStatus::PendingApproval->value, PayrollRunStatus::Approved->value, PayrollRunStatus::Paid->value])
                ->whereHas('items', fn ($items) => $items->where('salary_structure_id', $structure->id))
                ->exists();

            if ($usedByLockedRun) {
                throw new BusinessRuleException('This salary structure was used in a submitted or approved payroll run and cannot be removed.');
            }

            $previous = SalaryStructure::query()
                ->where('employee_id', $structure->employee_id)
                ->whereKeyNot($structure->id)
                ->orderByDesc('effective_from')
                ->first();

            $structure->delete();
            $previous?->forceFill(['effective_to' => null])->save();

            $this->auditLogger->log('salary_structure_deleted', $structure->employee, "Salary structure effective {$structure->effective_from->toDateString()} removed");
        });
    }

    private function lastReleasedRunFor(Employee $employee): ?PayrollRun
    {
        return PayrollRun::query()
            ->released()
            ->whereHas('items', fn ($items) => $items->where('employee_id', $employee->id))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();
    }

    /**
     * Assigned components with their values; empty values are not assigned.
     *
     * @param  array<int|string, string|null>  $values
     * @return array<int, string>
     */
    private function components(array $values): array
    {
        $values = collect($values)->filter(fn ($value): bool => filled($value));

        if ($values->isEmpty()) {
            return [];
        }

        $components = PayComponent::query()->whereIn('id', $values->keys()->map(fn ($id): int => (int) $id))->get()->keyBy('id');
        $assigned = [];

        foreach ($values as $id => $value) {
            $component = $components->get((int) $id);

            if ($component === null || ! $component->is_active) {
                throw new BusinessRuleException('One of the chosen pay components is no longer available.');
            }

            if (! is_numeric($value) || bccomp((string) $value, '0', 4) < 0) {
                throw new BusinessRuleException("The value for {$component->name} must be a positive number.");
            }

            if ($component->calculation->isPercentage() && bccomp((string) $value, '100', 4) > 0) {
                throw new BusinessRuleException("The percentage for {$component->name} cannot be more than 100.");
            }

            $assigned[$component->id] = bcadd((string) $value, '0', 4);
        }

        return $assigned;
    }
}
