<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOperationTest extends TestCase
{
    use RefreshDatabase;

    private User $inventoryOfficer;

    private Item $item;

    private Warehouse $main;

    private Warehouse $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->inventoryOfficer = $this->userWithRole(SystemRole::InventoryOfficer);
        $this->item = Item::factory()->create();
        $this->main = Warehouse::factory()->default()->create();
        $this->branch = Warehouse::factory()->create();
    }

    private function available(Warehouse $warehouse): string
    {
        return app(StockService::class)->available($this->item, $warehouse);
    }

    public function test_issue_requires_a_purpose_and_removes_stock(): void
    {
        app(StockService::class)->receive($this->item, $this->main, '10', '500');
        $payload = ['item_id' => $this->item->id, 'warehouse_id' => $this->main->id, 'quantity' => '3', 'moved_at' => now()->toDateString()];

        $this->actingAs($this->inventoryOfficer)->post(route('stock.operations.store', 'issue'), $payload)
            ->assertSessionHasErrors('notes');

        $this->actingAs($this->inventoryOfficer)->post(route('stock.operations.store', 'issue'), [...$payload, 'notes' => 'Workshop repairs'])
            ->assertRedirect(route('items.stock-card', $this->item));

        $this->assertSame('7.000', $this->available($this->main));
        $this->assertSame($this->inventoryOfficer->id, StockMovement::query()->latest('id')->first()->created_by);
    }

    public function test_issue_beyond_available_stock_shows_an_error(): void
    {
        $this->actingAs($this->inventoryOfficer)->from(route('stock.operations.create', 'issue'))
            ->post(route('stock.operations.store', 'issue'), [
                'item_id' => $this->item->id, 'warehouse_id' => $this->main->id, 'quantity' => '1',
                'moved_at' => now()->toDateString(), 'notes' => 'Site use',
            ])
            ->assertRedirect(route('stock.operations.create', 'issue'))
            ->assertSessionHas('error');

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_transfer_moves_stock_between_warehouses(): void
    {
        app(StockService::class)->receive($this->item, $this->main, '10', '500');

        $this->actingAs($this->inventoryOfficer)->post(route('stock.operations.store', 'transfer'), [
            'item_id' => $this->item->id, 'warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->branch->id,
            'quantity' => '4', 'moved_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('6.000', $this->available($this->main));
        $this->assertSame('4.000', $this->available($this->branch));
    }

    public function test_transfer_to_the_same_warehouse_fails_validation(): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('stock.operations.store', 'transfer'), [
            'item_id' => $this->item->id, 'warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->main->id,
            'quantity' => '1', 'moved_at' => now()->toDateString(),
        ])->assertSessionHasErrors('to_warehouse_id');
    }

    public function test_opening_balance_is_recorded_once(): void
    {
        $payload = ['item_id' => $this->item->id, 'warehouse_id' => $this->main->id, 'quantity' => '25', 'unit_cost' => '800', 'moved_at' => now()->toDateString()];

        $this->actingAs($this->inventoryOfficer)->post(route('stock.operations.store', 'opening'), $payload)->assertRedirect();
        $this->actingAs($this->inventoryOfficer)->from(route('stock.operations.create', 'opening'))
            ->post(route('stock.operations.store', 'opening'), $payload)
            ->assertSessionHas('error');

        $this->assertSame('25.000', $this->available($this->main));
        $this->assertSame(StockMovementType::OpeningBalance, StockMovement::query()->sole()->type);
    }

    public function test_roles_without_the_operation_permission_are_forbidden(): void
    {
        $procurement = $this->userWithRole(SystemRole::ProcurementOfficer);

        $this->actingAs($procurement)->get(route('stock.operations.create', 'issue'))->assertForbidden();
        $this->actingAs($procurement)->post(route('stock.operations.store', 'transfer'), [])->assertForbidden();
    }

    public function test_unknown_operations_are_not_routed(): void
    {
        $this->actingAs($this->inventoryOfficer)->get('/stock/steal')->assertNotFound();
    }

    public function test_a_manager_reverses_an_issue_recorded_by_someone_else(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);
        app(StockService::class)->receive($this->item, $this->main, '10', '500');
        $this->actingAs($this->inventoryOfficer);
        $issue = app(StockService::class)->issue($this->item, $this->main, '4', notes: 'Wrong item issued');

        $this->actingAs($manager)->post(route('stock.movements.reverse', $issue), ['reason' => 'Issued in error'])
            ->assertRedirect();

        $this->assertSame('10.000', $this->available($this->main));
        $this->assertNotNull($issue->reversal()->first());
    }

    public function test_reversal_is_refused_for_own_movements_and_document_movements(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);
        $this->actingAs($manager);
        $receipt = app(StockService::class)->receive($this->item, $this->main, '10', '500');
        $issue = app(StockService::class)->issue($this->item, $this->main, '1', notes: 'Test');

        $this->actingAs($manager)->post(route('stock.movements.reverse', $issue), ['reason' => 'My own mistake'])->assertForbidden();
        $this->actingAs($manager)->post(route('stock.movements.reverse', $receipt), ['reason' => 'Use the GRN instead'])->assertForbidden();
    }

    public function test_levels_and_movements_pages_show_stock_and_value(): void
    {
        app(StockService::class)->receive($this->item, $this->main, '4', '2500');

        $this->actingAs($this->inventoryOfficer)->get(route('stock.levels'))
            ->assertOk()
            ->assertSee($this->item->name)
            ->assertViewHas('totalValue', fn (string $value): bool => bccomp($value, '10000', 2) === 0);

        $this->actingAs($this->inventoryOfficer)->get(route('stock.movements', ['type' => 'goods_received']))
            ->assertOk()
            ->assertSee($this->item->name);

        $this->actingAs($this->inventoryOfficer)->get(route('stock.operations.create', 'transfer'))->assertOk();
        $this->actingAs($this->inventoryOfficer)->get(route('warehouses.index'))->assertOk();
    }
}
