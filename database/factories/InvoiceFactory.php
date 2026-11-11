<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invoice header without lines. Use InvoiceService in tests that need lines, totals, stock or payments.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'invoice_date' => today(),
            'due_date' => today()->addDays(30),
            'status' => InvoiceStatus::Draft,
            'currency' => 'UGX',
        ];
    }
}
