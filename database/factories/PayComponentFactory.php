<?php

namespace Database\Factories;

use App\Enums\PayComponentCalculation;
use App\Enums\PayComponentKind;
use App\Models\PayComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayComponent>
 */
class PayComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('ALW???')),
            'name' => fake()->words(2, true).' allowance',
            'kind' => PayComponentKind::Allowance,
            'calculation' => PayComponentCalculation::Fixed,
            'default_value' => '0',
            'is_taxable' => true,
            'is_nssf_applicable' => true,
            'is_active' => true,
        ];
    }

    /**
     * A deduction taken after tax and NSSF.
     */
    public function deduction(): static
    {
        return $this->state(fn (): array => [
            'code' => strtoupper(fake()->unique()->lexify('DED???')),
            'name' => fake()->words(2, true).' deduction',
            'kind' => PayComponentKind::Deduction,
            'is_taxable' => false,
            'is_nssf_applicable' => false,
        ]);
    }

    public function calculatedAs(PayComponentCalculation $calculation): static
    {
        return $this->state(fn (): array => ['calculation' => $calculation]);
    }
}
