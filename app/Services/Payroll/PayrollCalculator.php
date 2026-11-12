<?php

namespace App\Services\Payroll;

use App\Enums\PayComponentCalculation;
use App\Enums\PayComponentKind;
use App\Enums\PayrollLineKind;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Works out one employee's pay for one month (FR-PRL-05, docs/03 §3.6). A pure function of its input: the
 * effective salary structure and adjustments, and the effective statutory rule sets passed in as data. Nothing is
 * read from the database and no rate is hard-coded (FR-PRL-03). All arithmetic uses bcmath through Money.
 *
 * Order of work:
 *  1. Allowances: fixed, or a percentage of basic salary. Gross pay = basic + allowances.
 *  2. Deductions: fixed, a percentage of basic salary or a percentage of gross pay.
 *  3. Taxable pay = basic + taxable allowances − deductions taken before tax (never below zero).
 *     NSSF pay   = basic + NSSF-applicable allowances − deductions taken before NSSF (never below zero).
 *  4. PAYE from the resident or non-resident bands (marginal rates per band, plus any surcharge above a
 *     threshold), rounded once at the end.
 *  5. NSSF employee and employer contributions as percentages of NSSF pay.
 *  6. LST in the months listed by the schedule: the annual amount for the gross pay band divided by the number
 *     of instalment months.
 *  7. Net pay = gross − PAYE − NSSF (employee) − LST − other deductions.
 *
 * Rule formats (amounts and rates as decimal strings, rates in percent):
 *  - PAYE: {"bands": [{"from": "0", "to": "235000", "rate": "0"}, …, {"from": "410000", "to": null, "rate": "30"}],
 *           "surcharges": [{"above": "10000000", "rate": "10"}]}
 *  - NSSF: {"employee_rate": "5", "employer_rate": "10"}
 *  - LST:  {"bands": [{"from": "0", "to": "100000", "annual_amount": "0"}, …], "months": [7, 8, 9, 10]}
 *
 * @phpstan-type Component array{pay_component_id: int|null, code: string, name: string, kind: PayComponentKind, calculation: PayComponentCalculation, value: string, is_taxable: bool, is_nssf_applicable: bool}
 * @phpstan-type Line array{pay_component_id: int|null, code: string, description: string, kind: PayrollLineKind, amount: string}
 */
class PayrollCalculator
{
    private const SCALE = 8;

    /**
     * @param  array{basic_salary: string, is_resident: bool, month: int, components: list<Component>}  $employee
     * @param  array{paye_resident: array<string, mixed>, paye_non_resident: array<string, mixed>|null, nssf: array<string, mixed>, lst: array<string, mixed>|null}  $rules
     * @return array{basic_salary: string, total_allowances: string, gross_pay: string, taxable_pay: string, nssf_pay: string, paye: string, nssf_employee: string, nssf_employer: string, lst: string, other_deductions: string, total_deductions: string, net_pay: string, lines: list<Line>}
     */
    public function calculate(array $employee, array $rules): array
    {
        $basic = Money::of($employee['basic_salary']);

        if ($basic->isNegative()) {
            throw new InvalidArgumentException('Basic salary cannot be negative.');
        }

        $lines = [$this->line(null, 'BASIC', 'Basic salary', PayrollLineKind::Basic, $basic)];
        $allowances = Money::zero();
        $taxable = $basic;
        $nssfPay = $basic;

        foreach ($this->componentsOfKind($employee['components'], PayComponentKind::Allowance) as $component) {
            if ($component['calculation'] === PayComponentCalculation::PercentOfGross) {
                throw new InvalidArgumentException("Allowance {$component['code']} cannot be a percentage of gross pay.");
            }

            $amount = $this->amountOf($component, $basic, $basic);

            if ($amount->isZero()) {
                continue;
            }

            $allowances = $allowances->plus($amount);
            $taxable = $component['is_taxable'] ? $taxable->plus($amount) : $taxable;
            $nssfPay = $component['is_nssf_applicable'] ? $nssfPay->plus($amount) : $nssfPay;
            $lines[] = $this->line($component['pay_component_id'], $component['code'], $component['name'], PayrollLineKind::Allowance, $amount);
        }

        $gross = $basic->plus($allowances);
        $otherDeductions = Money::zero();
        $deductionLines = [];

        foreach ($this->componentsOfKind($employee['components'], PayComponentKind::Deduction) as $component) {
            $amount = $this->amountOf($component, $basic, $gross);

            if ($amount->isZero()) {
                continue;
            }

            $otherDeductions = $otherDeductions->plus($amount);
            $taxable = $component['is_taxable'] ? $taxable->minus($amount) : $taxable;
            $nssfPay = $component['is_nssf_applicable'] ? $nssfPay->minus($amount) : $nssfPay;
            $deductionLines[] = $this->line($component['pay_component_id'], $component['code'], $component['name'], PayrollLineKind::Deduction, $amount);
        }

        $taxable = $taxable->isNegative() ? Money::zero() : $taxable;
        $nssfPay = $nssfPay->isNegative() ? Money::zero() : $nssfPay;

        $payeRules = $employee['is_resident'] ? $rules['paye_resident'] : ($rules['paye_non_resident'] ?? throw new InvalidArgumentException('No PAYE rules for non-resident employees.'));
        $paye = $this->paye($taxable, $payeRules);
        $nssfEmployee = $nssfPay->percentage($this->rate($rules['nssf'], 'employee_rate'));
        $nssfEmployer = $nssfPay->percentage($this->rate($rules['nssf'], 'employer_rate'));
        $lst = $rules['lst'] !== null ? $this->lstInstalment($gross, $rules['lst'], $employee['month']) : null;

        $lines[] = $this->line(null, 'PAYE', 'PAYE (income tax)', PayrollLineKind::Statutory, $paye);
        $lines[] = $this->line(null, 'NSSF_EE', 'NSSF – employee '.$this->rate($rules['nssf'], 'employee_rate').' %', PayrollLineKind::Statutory, $nssfEmployee);

        if ($lst !== null) {
            $lines[] = $this->line(null, 'LST', 'Local Service Tax', PayrollLineKind::Statutory, $lst);
        }

        array_push($lines, ...$deductionLines);
        $lines[] = $this->line(null, 'NSSF_ER', 'NSSF – employer '.$this->rate($rules['nssf'], 'employer_rate').' %', PayrollLineKind::EmployerContribution, $nssfEmployer);

        $lst ??= Money::zero();
        $totalDeductions = Money::sum([$paye, $nssfEmployee, $lst, $otherDeductions]);

        return [
            'basic_salary' => $basic->toDecimal(),
            'total_allowances' => $allowances->toDecimal(),
            'gross_pay' => $gross->toDecimal(),
            'taxable_pay' => $taxable->toDecimal(),
            'nssf_pay' => $nssfPay->toDecimal(),
            'paye' => $paye->toDecimal(),
            'nssf_employee' => $nssfEmployee->toDecimal(),
            'nssf_employer' => $nssfEmployer->toDecimal(),
            'lst' => $lst->toDecimal(),
            'other_deductions' => $otherDeductions->toDecimal(),
            'total_deductions' => $totalDeductions->toDecimal(),
            'net_pay' => $gross->minus($totalDeductions)->toDecimal(),
            'lines' => $lines,
        ];
    }

    /**
     * PAYE on the taxable pay: each band's rate applies to the part of the pay that falls inside it, then any
     * surcharge applies to the pay above its threshold. Rounded once, at the end.
     *
     * @param  array<string, mixed>  $rules
     */
    public function paye(Money|string $taxable, array $rules): Money
    {
        $income = Money::of($taxable)->toDecimal();
        $tax = '0';

        foreach ($rules['bands'] ?? [] as $band) {
            $from = (string) $band['from'];
            $to = isset($band['to']) && $band['to'] !== '' ? (string) $band['to'] : null;

            if (bccomp($income, $from, self::SCALE) <= 0) {
                continue;
            }

            $top = $to === null || bccomp($income, $to, self::SCALE) < 0 ? $income : $to;
            $tax = bcadd($tax, bcdiv(bcmul(bcsub($top, $from, self::SCALE), (string) $band['rate'], self::SCALE), '100', self::SCALE), self::SCALE);
        }

        foreach ($rules['surcharges'] ?? [] as $surcharge) {
            $above = (string) $surcharge['above'];

            if (bccomp($income, $above, self::SCALE) > 0) {
                $tax = bcadd($tax, bcdiv(bcmul(bcsub($income, $above, self::SCALE), (string) $surcharge['rate'], self::SCALE), '100', self::SCALE), self::SCALE);
            }
        }

        return Money::of($tax);
    }

    /**
     * This month's LST instalment, or null when LST is not deducted in this month.
     *
     * @param  array<string, mixed>  $rules
     */
    public function lstInstalment(Money|string $gross, array $rules, int $month): ?Money
    {
        $months = array_map('intval', $rules['months'] ?? []);

        if (! in_array($month, $months, true)) {
            return null;
        }

        $pay = Money::of($gross)->toDecimal();
        $annual = '0';

        foreach ($rules['bands'] ?? [] as $band) {
            $from = (string) $band['from'];
            $to = isset($band['to']) && $band['to'] !== '' ? (string) $band['to'] : null;
            $aboveFrom = bccomp($pay, $from, self::SCALE) > 0 || bccomp($from, '0', self::SCALE) === 0;
            $withinTo = $to === null || bccomp($pay, $to, self::SCALE) <= 0;

            if ($aboveFrom && $withinTo) {
                $annual = (string) $band['annual_amount'];

                break;
            }
        }

        return Money::of($annual)->dividedBy(count($months));
    }

    /**
     * @param  Component  $component
     */
    private function amountOf(array $component, Money $basic, Money $gross): Money
    {
        $value = $component['value'];

        if (bccomp($value, '0', self::SCALE) < 0) {
            throw new InvalidArgumentException("Pay component {$component['code']} cannot be negative.");
        }

        return match ($component['calculation']) {
            PayComponentCalculation::Fixed => Money::of($value),
            PayComponentCalculation::PercentOfBasic => $basic->percentage($value),
            PayComponentCalculation::PercentOfGross => $gross->percentage($value),
        };
    }

    /**
     * @param  list<Component>  $components
     * @return list<Component>
     */
    private function componentsOfKind(array $components, PayComponentKind $kind): array
    {
        return array_values(array_filter($components, fn (array $component): bool => $component['kind'] === $kind));
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function rate(array $rules, string $key): string
    {
        if (! isset($rules[$key]) || ! is_numeric($rules[$key])) {
            throw new InvalidArgumentException("The NSSF rules have no {$key}.");
        }

        $rate = (string) $rules[$key];

        return str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate;
    }

    /**
     * @return Line
     */
    private function line(?int $componentId, string $code, string $description, PayrollLineKind $kind, Money $amount): array
    {
        return [
            'pay_component_id' => $componentId,
            'code' => $code,
            'description' => $description,
            'kind' => $kind,
            'amount' => $amount->toDecimal(),
        ];
    }
}
