<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Services\System\NumberingService;
use App\Services\System\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Seeds starting tax rates, the numbering configuration for every document type and the current-period
 * sequences. Safe to run repeatedly: nothing the organization has changed is overwritten.
 */
class SettingsSeeder extends Seeder
{
    public function run(SettingsService $settings, NumberingService $numbering): void
    {
        if (! TaxRate::query()->exists()) {
            TaxRate::query()->create(['name' => 'VAT', 'code' => 'VAT', 'rate' => '18.0000', 'is_active' => true])
                ->forceFill(['is_default' => true])->save();
            TaxRate::query()->create(['name' => 'Exempt', 'code' => 'EXEMPT', 'rate' => '0.0000', 'is_active' => true]);
        }

        foreach (DocumentType::cases() as $type) {
            Setting::query()->firstOrCreate(
                ['group' => 'numbering', 'key' => $type->value],
                ['value' => $type->defaultNumbering()],
            );
        }

        $settings->forget('numbering');

        foreach (DocumentType::cases() as $type) {
            DocumentSequence::query()->firstOrCreate(
                ['document_type' => $type->value, 'period' => $numbering->config($type)['reset']->period(now())],
                ['next_number' => 1],
            );
        }
    }
}
