<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Draft expenses by default. Recorded expenses should be created through ExpenseService so they are numbered.
 *
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_date' => today()->toDateString(),
            'expense_category_id' => ExpenseCategory::factory(),
            'description' => fake()->sentence(4),
            'amount' => '100000.00',
            'tax_amount' => '0.00',
            'payment_method' => PaymentMethod::Cash,
            'status' => ExpenseStatus::Draft,
        ];
    }

    public function recorded(): static
    {
        return $this->sequence(fn ($sequence) => [
            'number' => 'EXP-TEST-'.str_pad((string) ($sequence->index + 1), 5, '0', STR_PAD_LEFT),
            'status' => ExpenseStatus::Recorded,
            'recorded_at' => now(),
        ]);
    }
}
