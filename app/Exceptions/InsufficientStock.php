<?php

namespace App\Exceptions;

/**
 * Thrown when a movement would take an item's balance in a warehouse below zero.
 */
class InsufficientStock extends BusinessRuleException
{
    public static function for(string $itemName, string $warehouseName, string $available, string $requested): self
    {
        return new self(sprintf(
            'Not enough "%s" in %s: %s available, %s requested.',
            $itemName,
            $warehouseName,
            self::trim($available),
            self::trim($requested),
        ));
    }

    private static function trim(string $quantity): string
    {
        return str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;
    }
}
