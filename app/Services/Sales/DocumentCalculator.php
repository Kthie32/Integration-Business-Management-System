<?php

namespace App\Services\Sales;

use App\Enums\DiscountType;
use App\Exceptions\BusinessRuleException;
use App\Support\Money;

/**
 * Calculates the lines and totals of a sales document (quotation or invoice) with exact decimal arithmetic.
 *
 * Order of operations, per line:
 *  1. gross    = quantity × unit price, rounded to 2 dp
 *  2. net      = gross − line discount
 *  3. share    = the line's pro-rata part of the document discount (by net amount; any rounding remainder
 *                goes to the line with the largest net amount, so shares always add up to the discount)
 *  4. taxable  = net − share
 *  5. tax      = taxable × tax rate %, rounded to 2 dp
 *  6. total    = taxable + tax
 *
 * Document totals: subtotal = Σ gross; discount total = Σ line discounts + document discount;
 * tax total = Σ tax; total = subtotal − discount total + tax total.
 */
class DocumentCalculator
{
    private const WORKING_SCALE = 10;

    /**
     * @param  list<array{quantity: string, unit_price: string, discount_amount?: string|null, tax_rate?: string|null}>  $lines
     * @return array{
     *     lines: list<array{gross_amount: string, discount_amount: string, document_discount_share: string, taxable_amount: string, tax_amount: string, line_total: string}>,
     *     subtotal: string,
     *     line_discount_total: string,
     *     document_discount_amount: string,
     *     discount_total: string,
     *     tax_total: string,
     *     total: string
     * }
     */
    public function calculate(array $lines, ?DiscountType $discountType = null, string $discountValue = '0'): array
    {
        $prepared = [];

        foreach (array_values($lines) as $index => $line) {
            $quantity = $this->decimal($line['quantity']);
            $unitPrice = $this->decimal($line['unit_price']);

            if (bccomp($quantity, '0', self::WORKING_SCALE) <= 0) {
                throw new BusinessRuleException('Line '.($index + 1).': the quantity must be greater than zero.');
            }

            if (bccomp($unitPrice, '0', self::WORKING_SCALE) < 0) {
                throw new BusinessRuleException('Line '.($index + 1).': the unit price cannot be negative.');
            }

            $gross = Money::of(bcmul($quantity, $unitPrice, self::WORKING_SCALE));
            $lineDiscount = Money::of($this->decimal($line['discount_amount'] ?? '0'));

            if ($lineDiscount->isNegative() || $lineDiscount->greaterThan($gross)) {
                throw new BusinessRuleException('Line '.($index + 1).': the discount must be between zero and the line amount.');
            }

            $prepared[] = [
                'gross' => $gross,
                'discount' => $lineDiscount,
                'net' => $gross->minus($lineDiscount),
                'rate' => $this->decimal($line['tax_rate'] ?? '0'),
            ];
        }

        $netTotal = Money::sum(array_column($prepared, 'net'));
        $documentDiscount = $this->documentDiscount($netTotal, $discountType, $discountValue);
        $shares = $this->allocate($documentDiscount, array_column($prepared, 'net'));

        $calculatedLines = [];

        foreach ($prepared as $index => $line) {
            $taxable = $line['net']->minus($shares[$index]);
            $tax = $taxable->percentage($line['rate']);

            $calculatedLines[] = [
                'gross_amount' => $line['gross']->toDecimal(),
                'discount_amount' => $line['discount']->toDecimal(),
                'document_discount_share' => $shares[$index]->toDecimal(),
                'taxable_amount' => $taxable->toDecimal(),
                'tax_amount' => $tax->toDecimal(),
                'line_total' => $taxable->plus($tax)->toDecimal(),
            ];
        }

        $subtotal = Money::sum(array_column($prepared, 'gross'));
        $lineDiscounts = Money::sum(array_column($prepared, 'discount'));
        $discountTotal = $lineDiscounts->plus($documentDiscount);
        $taxTotal = Money::sum(array_column($calculatedLines, 'tax_amount'));

        return [
            'lines' => $calculatedLines,
            'subtotal' => $subtotal->toDecimal(),
            'line_discount_total' => $lineDiscounts->toDecimal(),
            'document_discount_amount' => $documentDiscount->toDecimal(),
            'discount_total' => $discountTotal->toDecimal(),
            'tax_total' => $taxTotal->toDecimal(),
            'total' => $subtotal->minus($discountTotal)->plus($taxTotal)->toDecimal(),
        ];
    }

    private function documentDiscount(Money $netTotal, ?DiscountType $type, string $value): Money
    {
        $value = $this->decimal($value);

        if ($type === null || bccomp($value, '0', self::WORKING_SCALE) === 0) {
            return Money::zero();
        }

        if (bccomp($value, '0', self::WORKING_SCALE) < 0) {
            throw new BusinessRuleException('The document discount cannot be negative.');
        }

        if ($type === DiscountType::Percentage) {
            if (bccomp($value, '100', self::WORKING_SCALE) > 0) {
                throw new BusinessRuleException('The document discount cannot be more than 100 %.');
            }

            return $netTotal->percentage($value);
        }

        $amount = Money::of($value);

        if ($amount->greaterThan($netTotal)) {
            throw new BusinessRuleException('The document discount cannot be more than the amount after line discounts.');
        }

        return $amount;
    }

    /**
     * Split the discount across lines in proportion to their net amounts.
     *
     * @param  list<Money>  $nets
     * @return list<Money>
     */
    private function allocate(Money $discount, array $nets): array
    {
        $netTotal = Money::sum($nets);

        if ($discount->isZero() || $netTotal->isZero()) {
            return array_map(fn (): Money => Money::zero(), $nets);
        }

        $shares = [];
        $largest = 0;

        foreach ($nets as $index => $net) {
            $shares[$index] = Money::of(bcdiv(
                bcmul($discount->toDecimal(), $net->toDecimal(), self::WORKING_SCALE),
                $netTotal->toDecimal(),
                self::WORKING_SCALE,
            ));

            if ($net->greaterThan($nets[$largest])) {
                $largest = $index;
            }
        }

        $remainder = $discount->minus(Money::sum($shares));
        $shares[$largest] = $shares[$largest]->plus($remainder);

        return $shares;
    }

    private function decimal(string|int|null $value): string
    {
        $value = trim(str_replace(',', '', (string) ($value ?? '0')));

        if ($value === '') {
            return '0';
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            throw new BusinessRuleException("\"{$value}\" is not a valid number.");
        }

        return $value;
    }
}
