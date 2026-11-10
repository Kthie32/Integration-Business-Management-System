<?php

namespace Tests\Feature\Settings;

use App\Enums\SystemRole;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxRateControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = User::factory()->withRole(SystemRole::FinanceOfficer)->create();
    }

    public function test_user_without_finance_settings_permission_is_forbidden(): void
    {
        $accountant = User::factory()->withRole(SystemRole::Accountant)->create();

        $this->actingAs($accountant)->get(route('settings.taxes.index'))->assertForbidden();
    }

    public function test_lists_tax_rates(): void
    {
        TaxRate::factory()->default()->create(['name' => 'VAT', 'code' => 'VAT']);

        $this->actingAs($this->financeOfficer)->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('VAT')
            ->assertSee('18 %');
    }

    public function test_the_first_tax_rate_becomes_the_default(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('settings.taxes.store'), [
            'name' => 'VAT', 'code' => 'vat', 'rate' => '18', 'is_active' => '1', 'is_default' => '0',
        ])->assertRedirect(route('settings.taxes.index'));

        $this->assertDatabaseHas('tax_rates', ['code' => 'VAT', 'rate' => '18.0000', 'is_default' => true]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => TaxRate::class]);
    }

    public function test_making_a_rate_the_default_removes_the_previous_default(): void
    {
        $vat = TaxRate::factory()->default()->create(['code' => 'VAT']);

        $this->actingAs($this->financeOfficer)->post(route('settings.taxes.store'), [
            'name' => 'Reduced', 'code' => 'RED', 'rate' => '7.5', 'is_active' => '1', 'is_default' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($vat->fresh()->is_default);
        $this->assertTrue(TaxRate::query()->where('code', 'RED')->sole()->is_default);
    }

    public function test_rejects_invalid_rates_and_duplicate_codes(): void
    {
        TaxRate::factory()->create(['code' => 'VAT']);

        $this->actingAs($this->financeOfficer)->post(route('settings.taxes.store'), [
            'name' => '', 'code' => 'vat', 'rate' => '120',
        ])->assertSessionHasErrors(['name', 'code', 'rate']);
    }

    public function test_the_default_rate_cannot_be_deactivated_or_lose_default_status(): void
    {
        $vat = TaxRate::factory()->default()->create(['code' => 'VAT']);

        $this->actingAs($this->financeOfficer)->put(route('settings.taxes.update', $vat), [
            'name' => 'VAT', 'code' => 'VAT', 'rate' => '18', 'is_active' => '0', 'is_default' => '1',
        ])->assertSessionHas('error', 'The default tax rate cannot be deactivated.');

        $this->actingAs($this->financeOfficer)->put(route('settings.taxes.update', $vat), [
            'name' => 'VAT', 'code' => 'VAT', 'rate' => '18', 'is_active' => '1', 'is_default' => '0',
        ])->assertSessionHas('error');

        $this->assertTrue($vat->fresh()->is_active);
        $this->assertTrue($vat->fresh()->is_default);
    }

    public function test_updating_a_rate_changes_it(): void
    {
        $rate = TaxRate::factory()->create(['code' => 'WHT', 'rate' => '6']);

        $this->actingAs($this->financeOfficer)->put(route('settings.taxes.update', $rate), [
            'name' => 'Withholding tax', 'code' => 'WHT', 'rate' => '6.5', 'is_active' => '0', 'is_default' => '0',
        ])->assertRedirect(route('settings.taxes.index'));

        $rate->refresh();
        $this->assertSame('6.5000', $rate->rate);
        $this->assertFalse($rate->is_active);
    }
}
