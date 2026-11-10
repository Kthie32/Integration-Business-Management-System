<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SUP-'.fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => '+2567'.fake()->numerify('########'),
            'email' => fake()->unique()->companyEmail(),
            'city' => 'Kampala',
            'payment_terms_days' => 30,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
