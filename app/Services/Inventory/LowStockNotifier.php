<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\User;
use App\Notifications\SystemMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Tells inventory and procurement staff when items fall to or below their minimum stock level (FR-INV-09):
 * immediately when a movement crosses the threshold, and in a daily summary.
 */
class LowStockNotifier
{
    /**
     * Called by the StockService after stock leaves a warehouse. Notifies only when this movement took the
     * item's total from above its minimum to at or below it, so staff are not alerted on every later issue.
     * The notification is sent after the transaction commits, so a rolled-back movement never alerts anyone.
     */
    public function checkAfterMovement(Item $item, string $quantityRemoved): void
    {
        $minimum = (string) $item->min_stock_level;

        if (bccomp($minimum, '0', 3) <= 0) {
            return;
        }

        $totalAfter = (string) ($item->stockLevels()->sum('quantity') ?: '0');
        $totalBefore = bcadd($totalAfter, $quantityRemoved, 3);

        if (bccomp($totalBefore, $minimum, 3) > 0 && bccomp($totalAfter, $minimum, 3) <= 0) {
            DB::afterCommit(fn () => $this->notify(
                'Low stock: '.$item->name,
                sprintf('%s (%s) is down to %s %s; the minimum is %s.', $item->name, $item->code, $this->trim($totalAfter), $item->unit, $this->trim($minimum)),
                route('items.show', $item),
            ));
        }
    }

    /**
     * Send one summary of every low-stock item. Returns the number of items reported.
     */
    public function sendDailySummary(): int
    {
        /** @var Collection<int, Item> $items */
        $items = Item::query()->active()->lowStock()->withStockOnHand()->orderBy('name')->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $list = $items->take(5)->map(fn (Item $item): string => "{$item->name} ({$this->trim((string) $item->stock_on_hand)} {$item->unit})")->implode(', ');
        $more = $items->count() > 5 ? sprintf(' and %d more', $items->count() - 5) : '';

        $this->notify(
            sprintf('%d item(s) at or below minimum stock', $items->count()),
            $list.$more.'.',
            route('items.index', ['stock' => 'low']),
        );

        return $items->count();
    }

    /**
     * Active users who manage items or receive stock.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return User::query()->active()->permission(['items.manage', 'stock.receive'])->get();
    }

    private function notify(string $title, string $message, string $url): void
    {
        Notification::send($this->recipients(), new SystemMessage($title, $message, $url, 'exclamation-triangle'));
    }

    private function trim(string $quantity): string
    {
        return str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;
    }
}
