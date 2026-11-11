<?php

namespace App\Services\Inventory;

use App\Exceptions\BusinessRuleException;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates and edits warehouses, keeping exactly one active default warehouse.
 */
class WarehouseService
{
    /**
     * @param  array{code: string, name: string, location?: string|null, is_active?: bool, is_default?: bool}  $data
     */
    public function save(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data): Warehouse {
            $makeDefault = (bool) ($data['is_default'] ?? false);
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($warehouse->exists && $warehouse->is_active && ! $isActive) {
                if ($warehouse->is_default) {
                    throw new BusinessRuleException('The default warehouse cannot be deactivated. Make another warehouse the default first.');
                }

                if ($warehouse->holdsStock()) {
                    throw new BusinessRuleException('This warehouse still holds stock. Transfer or adjust it to zero before deactivating.');
                }
            }

            if ($makeDefault && ! $isActive) {
                throw new BusinessRuleException('An inactive warehouse cannot be the default.');
            }

            $warehouse->fill([
                'code' => Str::upper($data['code']),
                'name' => $data['name'],
                'location' => $data['location'] ?? null,
                'is_active' => $isActive,
            ]);

            $isFirst = ! Warehouse::query()->whereKeyNot($warehouse->id)->exists();
            $warehouse->is_default = $warehouse->is_default || $makeDefault || $isFirst;
            $warehouse->save();

            if ($warehouse->is_default) {
                Warehouse::query()->whereKeyNot($warehouse->id)->where('is_default', true)->get()
                    ->each(fn (Warehouse $other) => $other->forceFill(['is_default' => false])->save());
            }

            return $warehouse;
        });
    }
}
