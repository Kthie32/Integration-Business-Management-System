<?php

namespace Tests\Feature\Settings;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_settings_link_opens_the_first_section_the_user_may_change(): void
    {
        $systemAdministrator = $this->userWithRole(SystemRole::SystemAdministrator);
        $financeOfficer = User::factory()->withRole(SystemRole::FinanceOfficer)->create();
        $employee = User::factory()->withRole(SystemRole::GeneralEmployee)->create();

        $this->actingAs($systemAdministrator)->get(route('settings.index'))->assertRedirect(route('settings.system.edit'));
        $this->actingAs($financeOfficer)->get(route('settings.index'))->assertRedirect(route('settings.finance.edit'));
        $this->actingAs($employee)->get(route('settings.index'))->assertForbidden();
    }

    public function test_finance_officer_cannot_change_system_settings(): void
    {
        $financeOfficer = User::factory()->withRole(SystemRole::FinanceOfficer)->create();

        $this->actingAs($financeOfficer)->put(route('settings.system.update'), ['date_format' => 'Y-m-d'])->assertForbidden();
    }

    public function test_system_administrator_saves_system_settings(): void
    {
        $systemAdministrator = $this->userWithRole(SystemRole::SystemAdministrator);

        $this->actingAs($systemAdministrator)->put(route('settings.system.update'), [
            'date_format' => 'Y-m-d',
            'email_notifications' => '1',
            'low_stock_check' => '0',
        ])->assertRedirect(route('settings.system.edit'));

        $system = app(SettingsService::class)->group('system');
        $this->assertSame('Y-m-d', $system['date_format']);
        $this->assertTrue($system['email_notifications']);
        $this->assertFalse($system['low_stock_check']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings_updated', 'user_id' => $systemAdministrator->id]);
    }

    public function test_rejects_unsupported_date_formats(): void
    {
        $systemAdministrator = $this->userWithRole(SystemRole::SystemAdministrator);

        $this->actingAs($systemAdministrator)->put(route('settings.system.update'), ['date_format' => 'U'])
            ->assertSessionHasErrors('date_format');
    }
}
