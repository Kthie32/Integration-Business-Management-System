<?php

namespace Tests\Feature\Settings;

use App\Enums\DocumentType;
use App\Enums\SystemRole;
use App\Models\User;
use App\Services\System\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NumberingSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = User::factory()->withRole(SystemRole::FinanceOfficer)->create();
    }

    public function test_shows_the_next_number_for_every_document_type(): void
    {
        $this->actingAs($this->financeOfficer)->get(route('settings.numbering.index'))
            ->assertOk()
            ->assertSee('INV-'.now()->format('Y').'-00001')
            ->assertSee('Goods received note');
    }

    public function test_saving_numbering_changes_the_format_and_next_number(): void
    {
        $this->actingAs($this->financeOfficer)->put(route('settings.numbering.update', DocumentType::Invoice), [
            'document_type' => 'invoice',
            'prefix' => 'inv',
            'padding' => '6',
            'reset' => 'never',
            'next_number' => '1200',
        ])->assertRedirect(route('settings.numbering.index'));

        $this->assertSame('INV-001200', DB::transaction(fn () => app(NumberingService::class)->next(DocumentType::Invoice)));
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings_updated', 'description' => 'Updated Invoice numbering']);
    }

    public function test_moving_the_next_number_backwards_is_refused_and_nothing_is_saved(): void
    {
        app(NumberingService::class)->setNextNumber(DocumentType::Quotation, 50);

        $this->actingAs($this->financeOfficer)->put(route('settings.numbering.update', DocumentType::Quotation), [
            'document_type' => 'quotation',
            'prefix' => 'QUO',
            'padding' => '5',
            'reset' => 'yearly',
            'next_number' => '10',
        ])->assertSessionHas('error');

        $this->assertSame('QT-'.now()->format('Y').'-00050', app(NumberingService::class)->preview(DocumentType::Quotation));
    }

    public function test_validation_errors_are_kept_to_the_submitted_document_type(): void
    {
        $this->actingAs($this->financeOfficer)->put(route('settings.numbering.update', DocumentType::Receipt), [
            'document_type' => 'receipt',
            'prefix' => 'R-T',
            'padding' => '20',
            'reset' => 'weekly',
            'next_number' => '0',
        ])->assertSessionHasErrorsIn('numbering_receipt', ['prefix', 'padding', 'reset', 'next_number']);
    }

    public function test_unknown_document_types_are_not_found(): void
    {
        $this->actingAs($this->financeOfficer)->put('/settings/numbering/purchase_order', [])->assertNotFound();
    }
}
