<?php

namespace Tests\Feature\Seeders;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Models\TaxRate;
use App\Services\System\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_default_vat_numbering_and_current_sequences(): void
    {
        $this->seed(SettingsSeeder::class);

        $vat = TaxRate::query()->where('code', 'VAT')->sole();
        $this->assertSame('18.0000', $vat->rate);
        $this->assertTrue($vat->is_default);
        $masterRecordTypes = array_filter(DocumentType::cases(), fn (DocumentType $type): bool => $type->isMasterRecord());
        $this->assertSame(count(DocumentType::cases()) - count($masterRecordTypes), DocumentSequence::query()->where('period', now()->format('Y'))->count());
        $this->assertSame(count($masterRecordTypes), DocumentSequence::query()->where('period', 'all')->count());
        $this->assertSame('INV', app(SettingsService::class)->get('numbering', 'invoice')['prefix']);
    }

    public function test_running_again_keeps_the_organizations_changes(): void
    {
        $this->seed(SettingsSeeder::class);
        TaxRate::query()->where('code', 'VAT')->update(['rate' => '16.0000']);
        app(SettingsService::class)->update('numbering', ['invoice' => ['prefix' => 'BILL', 'padding' => 4, 'reset' => 'never']]);

        $this->seed(SettingsSeeder::class);

        $this->assertSame(2, TaxRate::query()->count());
        $this->assertSame('16.0000', TaxRate::query()->where('code', 'VAT')->value('rate'));
        $this->assertSame('BILL', app(SettingsService::class)->get('numbering', 'invoice')['prefix']);
    }
}
