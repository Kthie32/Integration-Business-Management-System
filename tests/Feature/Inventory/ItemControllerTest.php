<?php

namespace Tests\Feature\Inventory;

use App\Enums\ItemType;
use App\Enums\SystemRole;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $inventoryOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->inventoryOfficer = $this->userWithRole(SystemRole::InventoryOfficer);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Steel bar 12mm',
            'type' => 'stock',
            'unit' => 'pcs',
            'selling_price' => '45000',
            'cost_price' => '38000',
            'min_stock_level' => '20',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_creates_item_with_sequential_code(): void
    {
        $response = $this->actingAs($this->inventoryOfficer)->post(route('items.store'), $this->payload());

        $item = Item::query()->sole();
        $response->assertRedirect(route('items.show', $item));
        $this->assertSame('ITM-0001', $item->code);
        $this->assertSame(ItemType::Stock, $item->type);
        $this->assertSame('20.000', (string) $item->min_stock_level);
    }

    public function test_validation_rejects_negative_prices_and_unknown_types(): void
    {
        $this->actingAs($this->inventoryOfficer)
            ->post(route('items.store'), $this->payload(['selling_price' => '-1', 'type' => 'gift', 'name' => '']))
            ->assertSessionHasErrors(['selling_price', 'type', 'name']);

        $this->assertSame(0, Item::query()->count());
    }

    public function test_view_only_roles_cannot_create_or_edit_items(): void
    {
        $accountant = $this->userWithRole(SystemRole::Accountant);
        $item = Item::factory()->create();

        $this->actingAs($accountant)->get(route('items.show', $item))->assertOk();
        $this->actingAs($accountant)->put(route('items.update', $item), $this->payload())->assertForbidden();
    }

    public function test_users_without_item_permission_are_forbidden(): void
    {
        $hrOfficer = $this->userWithRole(SystemRole::HumanResourceOfficer);

        $this->actingAs($hrOfficer)->get(route('items.index'))->assertForbidden();
    }

    public function test_items_with_stock_history_cannot_be_deleted_or_change_type(): void
    {
        $item = Item::factory()->create();
        app(StockService::class)->receive($item, Warehouse::factory()->create(), '1', '100');

        $this->actingAs($this->inventoryOfficer)->from(route('items.show', $item))
            ->delete(route('items.destroy', $item))
            ->assertSessionHas('error', 'This item has stock history and cannot be deleted. Deactivate it instead.');

        $this->actingAs($this->inventoryOfficer)->from(route('items.edit', $item))
            ->put(route('items.update', $item), $this->payload(['type' => 'service']))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($item);
        $this->assertSame(ItemType::Stock, $item->fresh()->type);
    }

    public function test_items_without_history_can_be_deleted(): void
    {
        $item = Item::factory()->create();

        $this->actingAs($this->inventoryOfficer)->delete(route('items.destroy', $item))->assertRedirect(route('items.index'));

        $this->assertSoftDeleted($item);
    }

    public function test_low_stock_filter_lists_items_at_or_below_their_minimum(): void
    {
        $warehouse = Warehouse::factory()->create();
        $low = Item::factory()->withMinimum('10')->create(['name' => 'Low item']);
        $fine = Item::factory()->withMinimum('10')->create(['name' => 'Fine item']);
        Item::factory()->create(['name' => 'No minimum item']);
        app(StockService::class)->receive($low, $warehouse, '10', '100');
        app(StockService::class)->receive($fine, $warehouse, '11', '100');

        $this->actingAs($this->inventoryOfficer)->get(route('items.index', ['stock' => 'low']))
            ->assertOk()
            ->assertSee('Low item')
            ->assertDontSee('Fine item')
            ->assertDontSee('No minimum item');
    }

    public function test_stock_card_shows_running_balance_from_an_opening_figure(): void
    {
        $item = Item::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = app(StockService::class);
        $stock->receive($item, $warehouse, '10', '100', movedAt: now()->subDays(10));
        $stock->receive($item, $warehouse, '5', '100', movedAt: now()->subDay());
        $stock->issue($item, $warehouse, '3', movedAt: now());

        $this->actingAs($this->inventoryOfficer)
            ->get(route('items.stock-card', [$item, 'from' => now()->subDays(2)->toDateString()]))
            ->assertOk()
            ->assertViewHas('opening', '10.000')
            ->assertViewHas('closing', '12.000')
            ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('balance')->all() === ['15.000', '12.000']);
    }

    public function test_stock_card_needs_stock_view_permission(): void
    {
        $item = Item::factory()->create();
        $user = User::factory()->create();
        $user->givePermissionTo('items.view');

        $this->actingAs($user)->get(route('items.stock-card', $item))->assertForbidden();
    }

    public function test_item_pages_render(): void
    {
        $item = Item::factory()->create();

        $this->actingAs($this->inventoryOfficer)->get(route('items.index'))->assertOk()->assertSee($item->name);
        $this->actingAs($this->inventoryOfficer)->get(route('items.create'))->assertOk();
        $this->actingAs($this->inventoryOfficer)->get(route('items.edit', $item))->assertOk();
        $this->actingAs($this->inventoryOfficer)->get(route('items.show', $item))->assertOk();
        $this->actingAs($this->inventoryOfficer)->get(route('items.stock-card', $item))->assertOk();
    }

    public function test_categories_nest_one_level_and_only_empty_ones_can_be_deleted(): void
    {
        $parent = ItemCategory::factory()->create(['name' => 'Building']);
        $child = ItemCategory::factory()->create(['name' => 'Cement', 'parent_id' => $parent->id]);

        $this->actingAs($this->inventoryOfficer)->from(route('item-categories.index'))
            ->post(route('item-categories.store'), ['name' => 'Too deep', 'parent_id' => $child->id])
            ->assertSessionHas('error', 'Categories can only be nested one level deep.');

        $this->actingAs($this->inventoryOfficer)->from(route('item-categories.index'))
            ->delete(route('item-categories.destroy', $parent))
            ->assertSessionHas('error');

        Item::factory()->create(['item_category_id' => $child->id]);
        $this->actingAs($this->inventoryOfficer)->from(route('item-categories.index'))
            ->delete(route('item-categories.destroy', $child))
            ->assertSessionHas('error');

        $this->actingAs($this->inventoryOfficer)->get(route('item-categories.index'))->assertOk()->assertSee('Cement');
        $this->assertSame(2, ItemCategory::query()->count());
    }

    public function test_warehouses_keep_one_default_and_refuse_deactivation_while_holding_stock(): void
    {
        $main = Warehouse::factory()->default()->create(['code' => 'MAIN']);
        $branch = Warehouse::factory()->create(['code' => 'BR1']);
        app(StockService::class)->receive(Item::factory()->create(), $branch, '1', '100');

        $this->actingAs($this->inventoryOfficer)
            ->put(route('warehouses.update', $branch), ['code' => 'BR1', 'name' => 'Branch', 'is_default' => '1', 'is_active' => '1'])
            ->assertRedirect(route('warehouses.index'));
        $this->assertTrue($branch->fresh()->is_default);
        $this->assertFalse($main->fresh()->is_default);

        $this->actingAs($this->inventoryOfficer)->from(route('warehouses.edit', $main))
            ->put(route('warehouses.update', $main), ['code' => 'MAIN', 'name' => 'Main', 'is_active' => '0'])
            ->assertRedirect(route('warehouses.index'));
        $this->assertFalse($main->fresh()->is_active);

        $this->actingAs($this->inventoryOfficer)->from(route('warehouses.edit', $branch))
            ->put(route('warehouses.update', $branch), ['code' => 'BR1', 'name' => 'Branch', 'is_default' => '0', 'is_active' => '0'])
            ->assertSessionHas('error');
        $this->assertTrue($branch->fresh()->is_active);
    }

    public function test_warehouse_codes_must_be_unique(): void
    {
        Warehouse::factory()->create(['code' => 'MAIN']);

        $this->actingAs($this->inventoryOfficer)
            ->post(route('warehouses.store'), ['code' => 'main', 'name' => 'Duplicate', 'is_active' => '1'])
            ->assertSessionHasErrors('code');
    }
}
