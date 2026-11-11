<?php

namespace App\Console\Commands;

use App\Services\Sales\InvoiceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ibms:invoices:overdue-check')]
#[Description('Notify finance staff about invoices that became overdue since the last check')]
class InvoiceOverdueCheckCommand extends Command
{
    public function handle(InvoiceService $invoices): int
    {
        $count = $invoices->notifyNewlyOverdue();

        $this->info("{$count} invoice(s) became overdue.");

        return self::SUCCESS;
    }
}
