<?php

namespace App\Services\Payroll;

use App\Enums\PayComponentCalculation;
use App\Enums\PayComponentKind;
use App\Enums\StatutoryRateCode;
use App\Exceptions\BusinessRuleException;
use App\Models\PayComponent;
use App\Models\PayrollRun;
use App\Models\StatutoryRate;
use App\Models\User;
use App\Services\System\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pay components (FR-PRL-01) and statutory rule sets (FR-PRL-03). Rule sets are effective-dated versions: a new
 * version closes the previous one the day before it starts. Versions used by an approved or paid run are locked.
 * Changing a version's rules clears its verification, so the change has to be confirmed again.
 */
class PayrollConfigurationService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveComponent(PayComponent $component, array $data, User $actor): PayComponent
    {
        $kind = PayComponentKind::from($data['kind']);
        $calculation = PayComponentCalculation::from($data['calculation']);

        if (! $calculation->allowedFor($kind)) {
            throw new BusinessRuleException('An allowance can be a fixed amount or a percentage of basic salary, not a percentage of gross pay.');
        }

        if ($component->exists && $component->isInUse() && ($component->kind !== $kind || $component->calculation !== $calculation)) {
            throw new BusinessRuleException("{$component->name} is already in use, so its type and calculation can no longer change. Create a new component instead.");
        }

        if ($calculation->isPercentage() && bccomp((string) $data['default_value'], '100', 4) > 0) {
            throw new BusinessRuleException('A percentage cannot be more than 100.');
        }

        $component->forceFill([
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'kind' => $kind,
            'calculation' => $calculation,
            'default_value' => bcadd((string) ($data['default_value'] ?? '0'), '0', 4),
            'is_taxable' => (bool) ($data['is_taxable'] ?? false),
            'is_nssf_applicable' => (bool) ($data['is_nssf_applicable'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
        ]);

        if (! $component->exists) {
            $component->forceFill(['created_by' => $actor->id]);
        }

        $component->save();

        return $component;
    }

    public function deleteComponent(PayComponent $component): void
    {
        if ($component->isInUse()) {
            throw new BusinessRuleException("{$component->name} is used in salary structures or payslips. Deactivate it instead.");
        }

        $component->delete();
    }

    /**
     * Add a new version of a rule set, effective from the given date.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRate(StatutoryRateCode $code, array $data, User $actor): StatutoryRate
    {
        $from = Carbon::parse($data['effective_from'])->startOfDay();
        $rules = $this->buildRules($code, $data);

        return DB::transaction(function () use ($code, $data, $from, $rules, $actor): StatutoryRate {
            $latest = StatutoryRate::query()->where('code', $code->value)->lockForUpdate()->orderByDesc('effective_from')->first();

            if ($latest !== null && $from->lessThanOrEqualTo($latest->effective_from)) {
                throw new BusinessRuleException("A new version must start after the current one ({$latest->effective_from->toDateString()}).");
            }

            $lastReleased = PayrollRun::query()->released()->orderByDesc('period_year')->orderByDesc('period_month')->first();

            if ($lastReleased !== null && $from->lessThanOrEqualTo($lastReleased->periodEnd())) {
                throw new BusinessRuleException("Payroll for {$lastReleased->periodLabel()} is already approved. A new version must start after {$lastReleased->periodEnd()->toDateString()}.");
            }

            if ($latest !== null && $latest->effective_to === null) {
                $latest->forceFill(['effective_to' => $from->copy()->subDay()->toDateString()])->save();
            }

            $rate = new StatutoryRate;
            $rate->forceFill([
                'code' => $code,
                'rules' => $rules,
                'effective_from' => $from->toDateString(),
                'source_reference' => filled($data['source_reference'] ?? null) ? trim((string) $data['source_reference']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'created_by' => $actor->id,
            ])->save();

            return $rate;
        });
    }

    /**
     * Change the rules of a version that no approved or paid run has used yet.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRate(StatutoryRate $rate, array $data): StatutoryRate
    {
        if ($rate->isLocked()) {
            throw new BusinessRuleException('This version was used by an approved payroll run and can no longer change. Add a new version instead.');
        }

        $rules = $this->buildRules($rate->code, $data);
        $changed = $this->canonical($rules) !== $this->canonical($rate->rules ?? []);

        $rate->forceFill([
            'rules' => $rules,
            'source_reference' => filled($data['source_reference'] ?? null) ? trim((string) $data['source_reference']) : null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);

        if ($changed) {
            $rate->forceFill(['verified_by' => null, 'verified_at' => null]);
        }

        $rate->save();

        return $rate;
    }

    /**
     * Record that an authorised person checked the version against the official publication (docs/07 D2).
     */
    public function verifyRate(StatutoryRate $rate, User $actor, ?string $source): StatutoryRate
    {
        if ($rate->isVerified()) {
            throw new BusinessRuleException('This version is already verified.');
        }

        $rate->forceFill([
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'source_reference' => filled($source) ? trim((string) $source) : $rate->source_reference,
        ])->save();

        $this->auditLogger->log('statutory_rate_verified', $rate, "{$rate->code->label()} from {$rate->effective_from->toDateString()} verified");

        return $rate;
    }

    /**
     * Turn submitted form fields into a validated rule set.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function buildRules(StatutoryRateCode $code, array $data): array
    {
        return match ($code) {
            StatutoryRateCode::PayeResident, StatutoryRateCode::PayeNonResident => $this->payeRules($data),
            StatutoryRateCode::Nssf => $this->nssfRules($data),
            StatutoryRateCode::Lst => $this->lstRules($data),
        };
    }

    /**
     * The rule sets seeded on installation: the developer's unverified understanding of the Ugandan rules
     * (docs/07 D2). They must be checked against current URA and NSSF publications before the first live run.
     *
     * @return array<string, mixed>
     */
    public static function defaultRules(StatutoryRateCode $code): array
    {
        return match ($code) {
            StatutoryRateCode::PayeResident => [
                'bands' => [
                    ['from' => '0', 'to' => '235000', 'rate' => '0'],
                    ['from' => '235000', 'to' => '335000', 'rate' => '10'],
                    ['from' => '335000', 'to' => '410000', 'rate' => '20'],
                    ['from' => '410000', 'to' => null, 'rate' => '30'],
                ],
                'surcharges' => [['above' => '10000000', 'rate' => '10']],
            ],
            StatutoryRateCode::PayeNonResident => [
                'bands' => [
                    ['from' => '0', 'to' => '335000', 'rate' => '10'],
                    ['from' => '335000', 'to' => '410000', 'rate' => '20'],
                    ['from' => '410000', 'to' => null, 'rate' => '30'],
                ],
                'surcharges' => [['above' => '10000000', 'rate' => '10']],
            ],
            StatutoryRateCode::Nssf => ['employee_rate' => '5', 'employer_rate' => '10'],
            StatutoryRateCode::Lst => [
                'bands' => [
                    ['from' => '0', 'to' => '100000', 'annual_amount' => '0'],
                    ['from' => '100000', 'to' => '200000', 'annual_amount' => '5000'],
                    ['from' => '200000', 'to' => '300000', 'annual_amount' => '10000'],
                    ['from' => '300000', 'to' => '400000', 'annual_amount' => '20000'],
                    ['from' => '400000', 'to' => '500000', 'annual_amount' => '30000'],
                    ['from' => '500000', 'to' => '600000', 'annual_amount' => '40000'],
                    ['from' => '600000', 'to' => '700000', 'annual_amount' => '60000'],
                    ['from' => '700000', 'to' => '800000', 'annual_amount' => '70000'],
                    ['from' => '800000', 'to' => '900000', 'annual_amount' => '80000'],
                    ['from' => '900000', 'to' => '1000000', 'annual_amount' => '90000'],
                    ['from' => '1000000', 'to' => null, 'annual_amount' => '100000'],
                ],
                'months' => [7, 8, 9, 10],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payeRules(array $data): array
    {
        $bands = $this->bands($data['bands'] ?? [], 'rate');

        foreach ($bands as $band) {
            if (bccomp($band['rate'], '100', 4) > 0) {
                throw new BusinessRuleException('A tax rate cannot be more than 100 %.');
            }
        }

        $surcharges = [];

        if (filled($data['surcharge_above'] ?? null) || filled($data['surcharge_rate'] ?? null)) {
            if (blank($data['surcharge_above'] ?? null) || blank($data['surcharge_rate'] ?? null)) {
                throw new BusinessRuleException('Give both the threshold and the rate of the additional tax, or leave both empty.');
            }

            $surcharges[] = ['above' => $this->decimal($data['surcharge_above']), 'rate' => $this->decimal($data['surcharge_rate'])];
        }

        return ['bands' => $bands, 'surcharges' => $surcharges];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function nssfRules(array $data): array
    {
        foreach (['employee_rate', 'employer_rate'] as $key) {
            if (! is_numeric($data[$key] ?? null) || bccomp((string) $data[$key], '0', 4) < 0 || bccomp((string) $data[$key], '100', 4) > 0) {
                throw new BusinessRuleException('NSSF rates must be between 0 and 100 %.');
            }
        }

        return ['employee_rate' => $this->decimal($data['employee_rate']), 'employer_rate' => $this->decimal($data['employer_rate'])];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function lstRules(array $data): array
    {
        $months = collect($data['months'] ?? [])->map(fn ($month): int => (int) $month)->filter(fn (int $month): bool => $month >= 1 && $month <= 12)->unique()->sort()->values()->all();

        if ($months === []) {
            throw new BusinessRuleException('Choose at least one month in which LST is deducted.');
        }

        return ['bands' => $this->bands($data['bands'] ?? [], 'annual_amount'), 'months' => $months];
    }

    /**
     * Bands must start at zero, follow on from each other without gaps, and only the last may be open-ended.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, string|null>>
     */
    private function bands(array $rows, string $valueKey): array
    {
        $bands = [];

        foreach ($rows as $row) {
            if (blank($row['from'] ?? null) && blank($row['to'] ?? null) && blank($row[$valueKey] ?? null)) {
                continue;
            }

            if (! is_numeric($row['from'] ?? null) || ! is_numeric($row[$valueKey] ?? null) || (filled($row['to'] ?? null) && ! is_numeric($row['to']))) {
                throw new BusinessRuleException('Every band needs a lower limit and a '.($valueKey === 'rate' ? 'rate' : 'yearly amount').'; the upper limit may be empty only for the last band.');
            }

            $bands[] = [
                'from' => $this->decimal($row['from']),
                'to' => filled($row['to'] ?? null) ? $this->decimal($row['to']) : null,
                $valueKey => $this->decimal($row[$valueKey]),
            ];
        }

        if ($bands === []) {
            throw new BusinessRuleException('Add at least one band.');
        }

        $previousTo = '0';

        foreach ($bands as $index => $band) {
            if (bccomp($band[$valueKey], '0', 4) < 0) {
                throw new BusinessRuleException('Rates and amounts cannot be negative.');
            }

            if (bccomp((string) $band['from'], (string) $previousTo, 4) !== 0) {
                throw new BusinessRuleException($index === 0
                    ? 'The first band must start at 0.'
                    : 'Each band must start where the previous one ends ('.$previousTo.').');
            }

            if ($band['to'] === null && $index !== array_key_last($bands)) {
                throw new BusinessRuleException('Only the last band may have no upper limit.');
            }

            if ($band['to'] !== null && bccomp($band['to'], $band['from'], 4) <= 0) {
                throw new BusinessRuleException('A band\'s upper limit must be above its lower limit.');
            }

            $previousTo = $band['to'];
        }

        return $bands;
    }

    /**
     * The database may store JSON object keys in a different order, so compare rule sets with sorted keys.
     *
     * @param  array<array-key, mixed>  $rules
     * @return array<array-key, mixed>
     */
    private function canonical(array $rules): array
    {
        if (! array_is_list($rules)) {
            ksort($rules);
        }

        return array_map(fn ($value) => is_array($value) ? $this->canonical($value) : $value, $rules);
    }

    private function decimal(mixed $value): string
    {
        $normalised = bcadd((string) $value, '0', 4);

        return str_contains($normalised, '.') ? rtrim(rtrim($normalised, '0'), '.') : $normalised;
    }
}
