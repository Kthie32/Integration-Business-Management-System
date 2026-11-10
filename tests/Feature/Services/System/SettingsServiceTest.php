<?php

namespace Tests\Feature\Services\System;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_defaults_until_a_value_is_saved(): void
    {
        $settings = app(SettingsService::class);

        $this->assertSame('UGX', $settings->get('currency', 'code'));
        $this->assertSame(0, $settings->get('currency', 'decimals'));
        $this->assertSame('UGX 1,250,000', $settings->formatMoney('1250000'));
    }

    public function test_saving_changes_values_immediately_and_records_only_what_changed(): void
    {
        $settings = app(SettingsService::class);
        $settings->get('finance', 'quotation_validity_days');

        $settings->update('finance', ['quotation_validity_days' => 14, 'invoice_payment_terms_days' => 30]);

        $this->assertSame(14, $settings->get('finance', 'quotation_validity_days'));
        $this->assertSame(14, app(SettingsService::class)->get('finance', 'quotation_validity_days'));
        $this->assertDatabaseCount('settings', 1);

        $log = AuditLog::query()->where('event', 'settings_updated')->sole();
        $this->assertSame(['quotation_validity_days' => 30], $log->old_values);
        $this->assertSame(['quotation_validity_days' => 14], $log->new_values);
    }

    public function test_saving_unchanged_values_writes_nothing(): void
    {
        app(SettingsService::class)->update('currency', ['code' => 'UGX']);

        $this->assertDatabaseCount('settings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rejects_unknown_settings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SettingsService::class)->update('currency', ['exchange_rate' => '3700']);
    }

    public function test_values_saved_elsewhere_are_read_after_the_cache_is_cleared(): void
    {
        $settings = app(SettingsService::class);
        $settings->get('organization', 'name');

        Setting::query()->create(['group' => 'organization', 'key' => 'name', 'value' => 'Kampala Traders Ltd']);
        $settings->forget('organization');

        $this->assertSame('Kampala Traders Ltd', $settings->organizationName());
    }
}
