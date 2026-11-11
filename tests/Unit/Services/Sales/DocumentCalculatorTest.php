<?php

namespace Tests\Unit\Services\Sales;

use App\Enums\DiscountType;
use App\Exceptions\BusinessRuleException;
use App\Services\Sales\DocumentCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentCalculatorTest extends TestCase
{
    private function calculate(array $lines, ?DiscountType $type = null, string $value = '0'): array
    {
        return (new DocumentCalculator)->calculate($lines, $type, $value);
    }

    /**
     * Each case: lines, discount type, discount value, expected [subtotal, discount_total, tax_total, total].
     *
     * @return array<string, array{list<array<string, string>>, DiscountType|null, string, array{string, string, string, string}}>
     */
    public static function totalsCases(): array
    {
        return [
            'single line with 18 % VAT' => [
                [['quantity' => '2', 'unit_price' => '50000', 'tax_rate' => '18']],
                null, '0',
                ['100000.00', '0.00', '18000.00', '118000.00'],
            ],
            'no tax' => [
                [['quantity' => '3', 'unit_price' => '1500', 'tax_rate' => '0']],
                null, '0',
                ['4500.00', '0.00', '0.00', '4500.00'],
            ],
            'fractional quantity and four-decimal price round half up at the line' => [
                [['quantity' => '2.5', 'unit_price' => '0.3333', 'tax_rate' => '0']],
                null, '0',
                ['0.83', '0.00', '0.00', '0.83'],
            ],
            'line discount is taken before tax' => [
                [['quantity' => '1', 'unit_price' => '100000', 'discount_amount' => '10000', 'tax_rate' => '18']],
                null, '0',
                ['100000.00', '10000.00', '16200.00', '106200.00'],
            ],
            'tax rounds half up per line' => [
                [['quantity' => '1', 'unit_price' => '0.25', 'tax_rate' => '18'], ['quantity' => '1', 'unit_price' => '0.25', 'tax_rate' => '18']],
                null, '0',
                ['0.50', '0.00', '0.10', '0.60'],
            ],
            'percentage document discount on mixed tax rates' => [
                [['quantity' => '1', 'unit_price' => '100000', 'tax_rate' => '18'], ['quantity' => '1', 'unit_price' => '100000', 'tax_rate' => '0']],
                DiscountType::Percentage, '10',
                ['200000.00', '20000.00', '16200.00', '196200.00'],
            ],
            'fixed document discount split pro-rata before tax' => [
                [['quantity' => '1', 'unit_price' => '300000', 'tax_rate' => '18'], ['quantity' => '1', 'unit_price' => '100000', 'tax_rate' => '0']],
                DiscountType::Amount, '40000',
                ['400000.00', '40000.00', '48600.00', '408600.00'],
            ],
            'uneven split keeps the discount exact' => [
                [['quantity' => '1', 'unit_price' => '100', 'tax_rate' => '0'], ['quantity' => '1', 'unit_price' => '100', 'tax_rate' => '0'], ['quantity' => '1', 'unit_price' => '100', 'tax_rate' => '0']],
                DiscountType::Amount, '100',
                ['300.00', '100.00', '0.00', '200.00'],
            ],
            '100 % discount gives a zero total' => [
                [['quantity' => '4', 'unit_price' => '2500', 'tax_rate' => '18']],
                DiscountType::Percentage, '100',
                ['10000.00', '10000.00', '0.00', '0.00'],
            ],
        ];
    }

    /**
     * @param  list<array<string, string>>  $lines
     * @param  array{string, string, string, string}  $expected
     */
    #[DataProvider('totalsCases')]
    public function test_calculates_document_totals(array $lines, ?DiscountType $type, string $value, array $expected): void
    {
        $result = $this->calculate($lines, $type, $value);

        $this->assertSame($expected, [$result['subtotal'], $result['discount_total'], $result['tax_total'], $result['total']]);
    }

    public function test_line_figures_add_up_to_the_document_totals(): void
    {
        $result = $this->calculate([
            ['quantity' => '1', 'unit_price' => '100', 'tax_rate' => '18'],
            ['quantity' => '1', 'unit_price' => '100', 'tax_rate' => '18'],
            ['quantity' => '1', 'unit_price' => '100', 'tax_rate' => '18'],
        ], DiscountType::Amount, '100');

        $this->assertSame(['33.34', '33.33', '33.33'], array_column($result['lines'], 'document_discount_share'));
        $this->assertSame('100.00', bcadd(bcadd($result['lines'][0]['document_discount_share'], $result['lines'][1]['document_discount_share'], 2), $result['lines'][2]['document_discount_share'], 2));
        $lineTotals = array_reduce(array_column($result['lines'], 'line_total'), fn (string $sum, string $total): string => bcadd($sum, $total, 2), '0');
        $this->assertSame($result['total'], $lineTotals);
    }

    public function test_the_rounding_remainder_goes_to_the_largest_line(): void
    {
        $result = $this->calculate([
            ['quantity' => '1', 'unit_price' => '10', 'tax_rate' => '0'],
            ['quantity' => '1', 'unit_price' => '20', 'tax_rate' => '0'],
        ], DiscountType::Amount, '0.01');

        $this->assertSame(['0.00', '0.01'], array_column($result['lines'], 'document_discount_share'));
    }

    /**
     * @return array<string, array{list<array<string, string>>, DiscountType|null, string}>
     */
    public static function invalidCases(): array
    {
        $line = ['quantity' => '1', 'unit_price' => '1000', 'tax_rate' => '18'];

        return [
            'zero quantity' => [[['quantity' => '0', 'unit_price' => '1000']], null, '0'],
            'negative price' => [[['quantity' => '1', 'unit_price' => '-1']], null, '0'],
            'line discount above the line amount' => [[[...$line, 'discount_amount' => '1000.01']], null, '0'],
            'percentage above 100' => [[$line], DiscountType::Percentage, '100.5'],
            'fixed discount above the net amount' => [[$line], DiscountType::Amount, '1000.01'],
            'negative document discount' => [[$line], DiscountType::Amount, '-5'],
            'not a number' => [[['quantity' => 'two', 'unit_price' => '1000']], null, '0'],
        ];
    }

    /**
     * @param  list<array<string, string>>  $lines
     */
    #[DataProvider('invalidCases')]
    public function test_rejects_impossible_amounts(array $lines, ?DiscountType $type, string $value): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->calculate($lines, $type, $value);
    }
}
