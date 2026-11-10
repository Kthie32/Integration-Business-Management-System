<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CUS-'.fake()->unique()->numerify('#####'),
            'type' => CustomerType::Company,
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
