<?php

namespace App\Console\Commands;

use App\Services\Inventory\LowStockNotifier;
use App\Services\System\SettingsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily summary of items at or below their minimum stock level (FR-INV-09). Honours Settings → System → low-stock check.
 */
#[Signature('ibms:stock:low-stock-check')]
#[Description('Notify inventory staff about items at or below their minimum stock level')]
class LowStockCheckCommand extends Command
{
    public function handle(SettingsService $settings, LowStockNotifier $notifier): int
    {
        if (! $settings->get('system', 'low_stock_check')) {
            $this->components->info('The daily low-stock check is turned off in Settings.');

            return self::SUCCESS;
        }

        $count = $notifier->sendDailySummary();

        $this->components->info($count === 0 ? 'No items are low on stock.' : "Reported {$count} low-stock item(s).");

        return self::SUCCESS;
    }
}
