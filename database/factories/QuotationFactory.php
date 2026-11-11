<?php

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A quotation header without lines. Use QuotationService in tests that need lines and totals.
 *
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'quote_date' => today(),
            'valid_until' => today()->addDays(30),
            'status' => QuotationStatus::Draft,
            'currency' => 'UGX',
        ];
    }

    public function status(QuotationStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'number' => $status === QuotationStatus::Draft ? null : 'QT-'.now()->format('Y').'-'.fake()->unique()->numerify('9####'),
        ]);
    }
}
