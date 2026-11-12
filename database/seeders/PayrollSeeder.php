<?php

namespace Database\Seeders;

use App\Enums\PayComponentCalculation;
use App\Enums\PayComponentKind;
use App\Enums\StatutoryRateCode;
use App\Models\PayComponent;
use App\Models\StatutoryRate;
use App\Services\Payroll\PayrollConfigurationService;
use Illuminate\Database\Seeder;

/**
 * Seeds the statutory rule sets and a starter list of pay components.
 *
 * The rule sets are the developer's unverified understanding of the Ugandan rules (docs/07 D2) and are seeded as
 * unverified: HR / Finance must check them against current URA and NSSF publications and mark them verified before
 * the first live run. Each rule set is seeded only when none exists for its code, and components only when there
 * are none, so the organization's own configuration is never changed.
 */
class PayrollSeeder extends Seeder
{
    public const EFFECTIVE_FROM = '2024-07-01';

    /**
     * Starter components: code => [name, kind, taxable, NSSF-applicable]. Generic deductions only (docs/07 D4).
     *
     * @var array<string, array{0: string, 1: PayComponentKind, 2: bool, 3: bool}>
     */
    public const COMPONENTS = [
        'HOUSING' => ['Housing allowance', PayComponentKind::Allowance, true, true],
        'TRANSPORT' => ['Transport allowance', PayComponentKind::Allowance, true, true],
        'LUNCH' => ['Lunch allowance', PayComponentKind::Allowance, true, true],
        'ADVANCE' => ['Salary advance recovery', PayComponentKind::Deduction, false, false],
        'LOAN' => ['Loan repayment', PayComponentKind::Deduction, false, false],
        'SACCO' => ['SACCO contribution', PayComponentKind::Deduction, false, false],
    ];

    public function run(): void
    {
        foreach (StatutoryRateCode::cases() as $code) {
            if (StatutoryRate::query()->where('code', $code->value)->exists()) {
                continue;
            }

            $rate = new StatutoryRate;
            $rate->forceFill([
                'code' => $code,
                'rules' => PayrollConfigurationService::defaultRules($code),
                'effective_from' => self::EFFECTIVE_FROM,
                'notes' => 'Seeded default — UNVERIFIED. Check against current URA / NSSF publications before the first live payroll.',
            ])->save();
        }

        if (PayComponent::query()->exists()) {
            return;
        }

        foreach (self::COMPONENTS as $code => [$name, $kind, $taxable, $nssf]) {
            $component = new PayComponent;
            $component->forceFill([
                'code' => $code,
                'name' => $name,
                'kind' => $kind,
                'calculation' => PayComponentCalculation::Fixed,
                'default_value' => '0',
                'is_taxable' => $taxable,
                'is_nssf_applicable' => $nssf,
                'is_active' => true,
            ])->save();
        }
    }
}
