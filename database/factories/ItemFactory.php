<?php

namespace Database\Factories;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ITM-'.fake()->unique()->numerify('#####'),
            'name' => ucfirst(fake()->words(2, true)),
            'type' => ItemType::Stock,
            'unit' => 'pcs',
            'selling_price' => '15000.0000',
            'cost_price' => '10000.0000',
            'min_stock_level' => '0.000',
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ItemType::Service, 'unit' => 'job']);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function withMinimum(string $minimum): static
    {
        return $this->state(fn (array $attributes) => ['min_stock_level' => $minimum]);
    }
}
