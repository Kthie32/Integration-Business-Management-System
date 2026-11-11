<?php

namespace App\Enums;

/**
 * Stock items are counted in warehouses; service items (labour, delivery, consulting) carry no stock.
 */
enum ItemType: string
{
    case Stock = 'stock';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Stock => 'Stock item',
            self::Service => 'Service',
        };
    }
}
