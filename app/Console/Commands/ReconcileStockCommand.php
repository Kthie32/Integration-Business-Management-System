<?php

namespace App\Console\Commands;

use App\Services\System\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes every balance from the movement ledger and reports differences (FR-INV-06).
 * With --fix, balances are corrected to match the ledger and each correction is audited.
 */
#[Signature('ibms:stock:reconcile {--fix : Correct balances that differ from the ledger}')]
#[Description('Check stock balances against the stock movement ledger')]
class ReconcileStockCommand extends Command
{
    public function handle(AuditLogger $auditLogger): int
    {
        $ledger = DB::table('stock_movements')
            ->groupBy('item_id', 'warehouse_id')
            ->selectRaw('item_id, warehouse_id, SUM(direction * quantity) AS ledger_quantity')
            ->get()
            ->keyBy(fn (object $row): string => "{$row->item_id}:{$row->warehouse_id}");

        $levels = DB::table('stock_levels')->get(['id', 'item_id', 'warehouse_id', 'quantity'])
            ->keyBy(fn (object $row): string => "{$row->item_id}:{$row->warehouse_id}");

        $mismatches = $ledger->keys()->merge($levels->keys())->unique()
            ->map(function (string $key) use ($ledger, $levels): ?array {
                $expected = bcadd((string) ($ledger->get($key)?->ledger_quantity ?? '0'), '0', 3);
                $actual = bcadd((string) ($levels->get($key)?->quantity ?? '0'), '0', 3);

                if (bccomp($expected, $actual, 3) === 0) {
                    return null;
                }

                [$itemId, $warehouseId] = array_map('intval', explode(':', $key));

                return ['item_id' => $itemId, 'warehouse_id' => $warehouseId, 'ledger' => $expected, 'balance' => $actual, 'level_id' => $levels->get($key)?->id];
            })
            ->filter()
            ->values();

        if ($mismatches->isEmpty()) {
            $this->components->info("All {$levels->count()} stock balances match the ledger.");

            return self::SUCCESS;
        }

        $this->components->error("{$mismatches->count()} balance(s) differ from the ledger.");
        $this->table(['Item ID', 'Warehouse ID', 'Ledger', 'Balance'], $mismatches->map(fn (array $row): array => [
            $row['item_id'], $row['warehouse_id'], $row['ledger'], $row['balance'],
        ]));

        if (! $this->option('fix')) {
            $this->line('Run again with --fix to correct the balances to match the ledger.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($mismatches, $auditLogger): void {
            foreach ($mismatches as $row) {
                if ($row['level_id'] === null) {
                    DB::table('stock_levels')->insert([
                        'item_id' => $row['item_id'],
                        'warehouse_id' => $row['warehouse_id'],
                        'quantity' => $row['ledger'],
                        'average_cost' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('stock_levels')->where('id', $row['level_id'])->lockForUpdate()->update(['quantity' => $row['ledger'], 'updated_at' => now()]);
                }

                $auditLogger->log(
                    'stock_reconciled',
                    null,
                    "Stock balance for item {$row['item_id']} in warehouse {$row['warehouse_id']} corrected to match the ledger",
                    ['quantity' => $row['balance']],
                    ['quantity' => $row['ledger']],
                );
            }
        });

        $this->components->info("Corrected {$mismatches->count()} balance(s).");

        return self::SUCCESS;
    }
}
