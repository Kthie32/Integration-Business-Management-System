<?php

namespace Tests\Feature\Settings;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'currency_code' => 'ugx',
            'currency_symbol' => 'USh',
            'currency_decimals' => '0',
            'quotation_validity_days' => '14',
            'invoice_payment_terms_days' => '45',
            'quotation_approval_threshold' => '5,000,000',
        ], $overrides);
    }

    public function test_system_administrator_cannot_change_finance_settings(): void
    {
        $systemAdministrator = $this->userWithRole(SystemRole::SystemAdministrator);

        $this->actingAs($systemAdministrator)->put(route('settings.finance.update'), $this->validPayload())->assertForbidden();
    }

    public function test_finance_officer_saves_currency_and_defaults(): void
    {
        $financeOfficer = User::factory()->withRole(SystemRole::FinanceOfficer)->create();

        $this->actingAs($financeOfficer)->get(route('settings.finance.edit'))->assertOk();
        $this->actingAs($financeOfficer)->put(route('settings.finance.update'), $this->validPayload())
            ->assertRedirect(route('settings.finance.edit'));

        $settings = app(SettingsService::class);
        $this->assertSame('UGX', $settings->get('currency', 'code'));
        $this->assertSame('USh', $settings->get('currency', 'symbol'));
        $this->assertSame(14, $settings->get('finance', 'quotation_validity_days'));
        $this->assertSame(45, $settings->get('finance', 'invoice_payment_terms_days'));
        $this->assertSame('5000000.00', $settings->get('finance', 'quotation_approval_threshold'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings_updated', 'user_id' => $financeOfficer->id]);
    }

    public function test_rejects_invalid_currency_and_defaults(): void
    {
        $financeOfficer = User::factory()->withRole(SystemRole::FinanceOfficer)->create();

        $this->actingAs($financeOfficer)->put(route('settings.finance.update'), $this->validPayload([
            'currency_code' => 'SHILLING',
            'currency_decimals' => '4',
            'quotation_validity_days' => '0',
            'quotation_approval_threshold' => '-5',
        ]))->assertSessionHasErrors(['currency_code', 'currency_decimals', 'quotation_validity_days', 'quotation_approval_threshold']);

        $this->assertDatabaseCount('settings', 0);
    }
}
