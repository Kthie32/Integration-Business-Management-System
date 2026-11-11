<?php

namespace Database\Factories;

use App\Enums\GoodsReceiptStatus;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft goods received note with no lines. Post real receipts through the GoodsReceiptService so stock moves.
 *
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'received_at' => now()->toDateString(),
            'status' => GoodsReceiptStatus::Draft,
            'total' => '0.00',
        ];
    }
}
