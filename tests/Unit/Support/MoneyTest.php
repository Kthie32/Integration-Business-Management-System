<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{int|string, string}>
     */
    public static function creationCases(): array
    {
        return [
            'integer' => [1250000, '1250000.00'],
            'string with decimals' => ['19.99', '19.99'],
            'rounds half up at the third decimal' => ['19.995', '20.00'],
            'rounds down below half' => ['19.994', '19.99'],
            'negative rounds half away from zero' => ['-19.995', '-20.00'],
            'thousands separators are ignored' => ['1,250,000.50', '1250000.50'],
            'negative zero becomes zero' => ['-0.001', '0.00'],
        ];
    }

    #[DataProvider('creationCases')]
    public function test_creates_amounts_rounded_to_two_decimals(int|string $input, string $expected): void
    {
        $this->assertSame($expected, Money::of($input)->toDecimal());
    }

    #[TestWith(['abc'])]
    #[TestWith(['12.5.3'])]
    #[TestWith([''])]
    #[TestWith(['1e5'])]
    public function test_rejects_values_that_are_not_decimal_numbers(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of($input);
    }

    public function test_adds_and_subtracts_without_floating_point_error(): void
    {
        $this->assertSame('0.30', Money::of('0.10')->plus('0.20')->toDecimal());
        $this->assertSame('-50.25', Money::of('100')->minus('150.25')->toDecimal());
        $this->assertSame('0.60', Money::sum(['0.10', '0.20', Money::of('0.30')])->toDecimal());
    }

    /**
     * @return array<string, array{string, int|string, string}>
     */
    public static function multiplicationCases(): array
    {
        return [
            'whole quantity' => ['1500.00', 3, '4500.00'],
            'fractional quantity' => ['1500.00', '2.5', '3750.00'],
            'result rounds half up' => ['0.15', '0.5', '0.08'],
            'three-decimal quantity' => ['333.33', '1.333', '444.33'],
        ];
    }

    #[DataProvider('multiplicationCases')]
    public function test_multiplies_by_quantities(string $amount, int|string $factor, string $expected): void
    {
        $this->assertSame($expected, Money::of($amount)->multipliedBy($factor)->toDecimal());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function percentageCases(): array
    {
        return [
            'VAT 18 percent' => ['1000000', '18', '180000.00'],
            'fractional rate' => ['200', '7.5', '15.00'],
            'rounds half up' => ['10.05', '18', '1.81'],
            'zero rate' => ['5000', '0', '0.00'],
        ];
    }

    #[DataProvider('percentageCases')]
    public function test_calculates_percentages(string $amount, string $rate, string $expected): void
    {
        $this->assertSame($expected, Money::of($amount)->percentage($rate)->toDecimal());
    }

    public function test_divides_and_refuses_division_by_zero(): void
    {
        $this->assertSame('33.33', Money::of('100')->dividedBy(3)->toDecimal());

        $this->expectException(InvalidArgumentException::class);
        Money::of('100')->dividedBy('0.00');
    }

    public function test_compares_amounts(): void
    {
        $amount = Money::of('100.00');

        $this->assertTrue($amount->equals('100'));
        $this->assertTrue($amount->greaterThan('99.99'));
        $this->assertTrue($amount->lessThan('100.01'));
        $this->assertTrue(Money::zero()->isZero());
        $this->assertTrue(Money::of('-1')->isNegative());
        $this->assertTrue($amount->isPositive());
        $this->assertSame('-100.00', $amount->negate()->toDecimal());
    }

    /**
     * @return array<string, array{string, int, string, string}>
     */
    public static function formattingCases(): array
    {
        return [
            'UGX with no decimals' => ['1250000.00', 0, 'UGX', 'UGX 1,250,000'],
            'UGX rounds half up for display' => ['1250.50', 0, 'UGX', 'UGX 1,251'],
            'two decimals' => ['1234.5', 2, 'USD', 'USD 1,234.50'],
            'negative amount' => ['-4500', 0, 'UGX', '-UGX 4,500'],
            'small negative that rounds to zero is not negative' => ['-0.40', 0, 'UGX', 'UGX 0'],
            'no symbol' => ['999', 0, '', '999'],
            'exactly one thousand' => ['1000', 0, 'UGX', 'UGX 1,000'],
        ];
    }

    #[DataProvider('formattingCases')]
    public function test_formats_for_display(string $amount, int $decimals, string $symbol, string $expected): void
    {
        $this->assertSame($expected, Money::of($amount)->format($decimals, $symbol));
    }
}
