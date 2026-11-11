<?php

namespace App\Console\Commands;

use App\Services\Sales\QuotationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ibms:quotations:expire')]
#[Description('Mark approved or sent quotations whose validity date has passed as expired')]
class ExpireQuotationsCommand extends Command
{
    public function handle(QuotationService $quotations): int
    {
        $expired = $quotations->expireOverdue();

        $this->info("Expired {$expired} quotation(s).");

        return self::SUCCESS;
    }
}
