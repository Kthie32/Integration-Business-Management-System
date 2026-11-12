<?php

namespace Tests\Unit\Services\Payroll;

use App\Enums\PayComponentCalculation;
use App\Enums\PayComponentKind;
use App\Enums\PayrollLineKind;
use App\Enums\StatutoryRateCode;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollConfigurationService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PayrollCalculatorTest extends TestCase
{
    /**
     * @return array{paye_resident: array<string, mixed>, paye_non_resident: array<string, mixed>, nssf: array<string, mixed>, lst: array<string, mixed>}
     */
    private function rules(): array
    {
        return [
            'paye_resident' => PayrollConfigurationService::defaultRules(StatutoryRateCode::PayeResident),
            'paye_non_resident' => PayrollConfigurationService::defaultRules(StatutoryRateCode::PayeNonResident),
            'nssf' => PayrollConfigurationService::defaultRules(StatutoryRateCode::Nssf),
            'lst' => PayrollConfigurationService::defaultRules(StatutoryRateCode::Lst),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function component(string $code, PayComponentKind $kind, string $value, PayComponentCalculation $calculation = PayComponentCalculation::Fixed, bool $taxable = true, bool $nssf = true): array
    {
        return [
            'pay_component_id' => null, 'code' => $code, 'name' => ucfirst(strtolower($code)), 'kind' => $kind,
            'calculation' => $calculation, 'value' => $value, 'is_taxable' => $taxable, 'is_nssf_applicable' => $nssf,
        ];
    }

    /**
     * Worked examples per resident PAYE band (docs/07 D2 bands): taxable pay => PAYE.
     *
     * @return array<string, array{string, string}>
     */
    public static function residentPayeCases(): array
    {
        return [
            'below the threshold' => ['200000', '0.00'],
            'exactly at the threshold' => ['235000', '0.00'],
            'second band (10 %)' => ['300000', '6500.00'],
            'top of the second band' => ['335000', '10000.00'],
            'third band (20 % + 10,000)' => ['400000', '23000.00'],
            'top of the third band' => ['410000', '25000.00'],
            'fourth band (30 % + 25,000)' => ['1000000', '202000.00'],
            'additional 10 % above 10 million' => ['12000000', '3702000.00'],
            'cents are kept and rounded half up' => ['235000.05', '0.01'],
        ];
    }

    #[DataProvider('residentPayeCases')]
    public function test_resident_paye_per_band(string $taxable, string $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator)->paye($taxable, $this->rules()['paye_resident'])->toDecimal());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function nonResidentPayeCases(): array
    {
        return [
            'first band taxed from zero' => ['300000', '30000.00'],
            'third band' => ['1000000', '225500.00'],
        ];
    }

    #[DataProvider('nonResidentPayeCases')]
    public function test_non_resident_paye(string $taxable, string $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator)->paye($taxable, $this->rules()['paye_non_resident'])->toDecimal());
    }

    /**
     * Monthly gross pay => LST instalment in an LST month (yearly amount over four instalments).
     *
     * @return array<string, array{string, string}>
     */
    public static function lstCases(): array
    {
        return [
            'up to 100,000 pays nothing' => ['100000', '0.00'],
            'just above 100,000' => ['150000', '1250.00'],
            'top of a band belongs to that band' => ['500000', '7500.00'],
            'above 1 million pays the maximum' => ['1300000', '25000.00'],
        ];
    }

    #[DataProvider('lstCases')]
    public function test_lst_instalment_by_gross_pay_band(string $gross, string $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator)->lstInstalment($gross, $this->rules()['lst'], 7)?->toDecimal());
    }

    public function test_lst_is_only_deducted_in_the_scheduled_months(): void
    {
        $this->assertNull((new PayrollCalculator)->lstInstalment('1300000', $this->rules()['lst'], 11));
    }

    public function test_full_payslip_with_allowances_deductions_nssf_and_lst(): void
    {
        $result = (new PayrollCalculator)->calculate([
            'basic_salary' => '1000000',
            'is_resident' => true,
            'month' => 7,
            'components' => [
                $this->component('HOUSING', PayComponentKind::Allowance, '200000'),
                $this->component('LUNCH', PayComponentKind::Allowance, '100000', taxable: false, nssf: false),
                $this->component('SACCO', PayComponentKind::Deduction, '50000', taxable: false, nssf: false),
                $this->component('LOAN', PayComponentKind::Deduction, '10', PayComponentCalculation::PercentOfBasic, false, false),
            ],
        ], $this->rules());

        $this->assertSame('1000000.00', $result['basic_salary']);
        $this->assertSame('300000.00', $result['total_allowances']);
        $this->assertSame('1300000.00', $result['gross_pay']);
        $this->assertSame('1200000.00', $result['taxable_pay']);
        $this->assertSame('1200000.00', $result['nssf_pay']);
        $this->assertSame('262000.00', $result['paye']);
        $this->assertSame('60000.00', $result['nssf_employee']);
        $this->assertSame('120000.00', $result['nssf_employer']);
        $this->assertSame('25000.00', $result['lst']);
        $this->assertSame('150000.00', $result['other_deductions']);
        $this->assertSame('497000.00', $result['total_deductions']);
        $this->assertSame('803000.00', $result['net_pay']);

        $this->assertSame(
            ['BASIC', 'HOUSING', 'LUNCH', 'PAYE', 'NSSF_EE', 'LST', 'SACCO', 'LOAN', 'NSSF_ER'],
            array_column($result['lines'], 'code'),
        );
        $this->assertSame(PayrollLineKind::EmployerContribution, $result['lines'][8]['kind']);
    }

    public function test_outside_lst_months_there_is_no_lst_line(): void
    {
        $result = (new PayrollCalculator)->calculate(['basic_salary' => '1000000', 'is_resident' => true, 'month' => 11, 'components' => []], $this->rules());

        $this->assertSame('0.00', $result['lst']);
        $this->assertNotContains('LST', array_column($result['lines'], 'code'));
        $this->assertSame('1000000.00', $result['gross_pay']);
        $this->assertSame('202000.00', $result['paye']);
        $this->assertSame('50000.00', $result['nssf_employee']);
        $this->assertSame('748000.00', $result['net_pay']);
    }

    public function test_deductions_flagged_before_tax_reduce_taxable_and_nssf_pay(): void
    {
        $result = (new PayrollCalculator)->calculate([
            'basic_salary' => '500000',
            'is_resident' => true,
            'month' => 11,
            'components' => [$this->component('PENSION', PayComponentKind::Deduction, '20', PayComponentCalculation::PercentOfGross, true, true)],
        ], $this->rules());

        $this->assertSame('100000.00', $result['other_deductions']);
        $this->assertSame('400000.00', $result['taxable_pay']);
        $this->assertSame('400000.00', $result['nssf_pay']);
        $this->assertSame('23000.00', $result['paye']);
        $this->assertSame('20000.00', $result['nssf_employee']);
    }

    public function test_non_resident_rules_are_used_for_non_residents(): void
    {
        $result = (new PayrollCalculator)->calculate(['basic_salary' => '300000', 'is_resident' => false, 'month' => 11, 'components' => []], $this->rules());

        $this->assertSame('30000.00', $result['paye']);
    }

    public function test_an_allowance_cannot_be_a_percentage_of_gross_pay(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PayrollCalculator)->calculate([
            'basic_salary' => '500000', 'is_resident' => true, 'month' => 1,
            'components' => [$this->component('BONUS', PayComponentKind::Allowance, '10', PayComponentCalculation::PercentOfGross)],
        ], $this->rules());
    }

    public function test_rates_come_from_the_rules_not_the_code(): void
    {
        $rules = [...$this->rules(), 'nssf' => ['employee_rate' => '6', 'employer_rate' => '12']];

        $result = (new PayrollCalculator)->calculate(['basic_salary' => '1000000', 'is_resident' => true, 'month' => 11, 'components' => []], $rules);

        $this->assertSame('60000.00', $result['nssf_employee']);
        $this->assertSame('120000.00', $result['nssf_employer']);
    }
}
