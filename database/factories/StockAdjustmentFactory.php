<?php

namespace Database\Factories;

use App\Enums\StockAdjustmentStatus;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending adjustment with no lines. Create real adjustments through the StockAdjustmentService.
 *
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'ADJ-'.fake()->unique()->numerify('#####'),
            'warehouse_id' => Warehouse::factory(),
            'adjusted_at' => now()->toDateString(),
            'reason' => 'Stock count difference',
            'status' => StockAdjustmentStatus::Pending,
        ];
    }
}
