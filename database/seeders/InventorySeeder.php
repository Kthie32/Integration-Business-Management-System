<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Seeds the default warehouse (FR-INV-03). Does nothing once any warehouse exists.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        if (Warehouse::query()->exists()) {
            return;
        }

        $warehouse = new Warehouse(['code' => 'MAIN', 'name' => 'Main Store', 'is_active' => true]);
        $warehouse->is_default = true;
        $warehouse->save();
    }
}
