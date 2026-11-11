<?php

namespace Tests\Feature\Inventory;

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\Warehouse;
use App\Notifications\SystemMessage;
use App\Services\Inventory\StockService;
use App\Services\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StockCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->warehouse = Warehouse::factory()->default()->create();
    }

    public function test_reconcile_passes_when_balances_match_the_ledger(): void
    {
        $item = Item::factory()->create();
        app(StockService::class)->receive($item, $this->warehouse, '10', '100');
        app(StockService::class)->issue($item, $this->warehouse, '3');

        $this->artisan('ibms:stock:reconcile')->assertSuccessful();
    }

    public function test_reconcile_reports_a_tampered_balance_and_fix_corrects_it(): void
    {
        $item = Item::factory()->create();
        app(StockService::class)->receive($item, $this->warehouse, '10', '100');
        DB::table('stock_levels')->where('item_id', $item->id)->update(['quantity' => '12']);

        $this->artisan('ibms:stock:reconcile')->assertFailed();

        $this->artisan('ibms:stock:reconcile --fix')->assertSuccessful();

        $this->assertSame('10.000', app(StockService::class)->available($item, $this->warehouse));
        $this->assertTrue(AuditLog::query()->where('event', 'stock_reconciled')->exists());
        $this->artisan('ibms:stock:reconcile')->assertSuccessful();
    }

    public function test_daily_check_notifies_inventory_staff_about_low_items(): void
    {
        $inventoryOfficer = $this->userWithRole(SystemRole::InventoryOfficer);
        $accountant = $this->userWithRole(SystemRole::Accountant);
        $item = Item::factory()->withMinimum('5')->create(['name' => 'Printer paper']);
        app(StockService::class)->receive($item, $this->warehouse, '5', '100');
        Notification::fake();

        $this->artisan('ibms:stock:low-stock-check')->assertSuccessful();

        Notification::assertSentTo($inventoryOfficer, SystemMessage::class, fn (SystemMessage $message): bool => str_contains($message->message, 'Printer paper'));
        Notification::assertNotSentTo($accountant, SystemMessage::class);
    }

    public function test_daily_check_does_nothing_when_turned_off_in_settings(): void
    {
        $this->userWithRole(SystemRole::InventoryOfficer);
        Item::factory()->withMinimum('5')->create();
        app(SettingsService::class)->update('system', ['low_stock_check' => false]);
        Notification::fake();

        $this->artisan('ibms:stock:low-stock-check')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_an_issue_that_crosses_the_minimum_alerts_once(): void
    {
        $inventoryOfficer = $this->userWithRole(SystemRole::InventoryOfficer);
        $item = Item::factory()->withMinimum('5')->create();
        app(StockService::class)->receive($item, $this->warehouse, '8', '100');
        Notification::fake();

        app(StockService::class)->issue($item, $this->warehouse, '3');
        app(StockService::class)->issue($item, $this->warehouse, '1');

        Notification::assertSentToTimes($inventoryOfficer, SystemMessage::class, 1);
    }
}
