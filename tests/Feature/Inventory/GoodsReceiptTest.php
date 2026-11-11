<?php

namespace Tests\Feature\Inventory;

use App\Enums\GoodsReceiptStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $inventoryOfficer;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Item $cement;

    private Item $nails;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->inventoryOfficer = $this->userWithRole(SystemRole::InventoryOfficer);
        $this->supplier = Supplier::factory()->create();
        $this->warehouse = Warehouse::factory()->default()->create();
        $this->cement = Item::factory()->create(['name' => 'Cement 50kg']);
        $this->nails = Item::factory()->create(['name' => 'Nails 4 inch']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'supplier_invoice_ref' => 'DN-4471',
            'lines' => [
                ['item_id' => $this->cement->id, 'quantity' => '20', 'unit_cost' => '32000'],
                ['item_id' => $this->nails->id, 'quantity' => '2.5', 'unit_cost' => '7000'],
                ['item_id' => '', 'quantity' => '', 'unit_cost' => ''],
            ],
            ...$overrides,
        ];
    }

    public function test_saving_a_draft_records_lines_and_total_without_touching_stock(): void
    {
        $response = $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload());

        $receipt = GoodsReceipt::query()->sole();
        $response->assertRedirect(route('goods-receipts.show', $receipt));
        $this->assertSame(GoodsReceiptStatus::Draft, $receipt->status);
        $this->assertNull($receipt->number);
        $this->assertCount(2, $receipt->items);
        $this->assertSame('657500.00', (string) $receipt->total);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_posting_adds_stock_and_assigns_a_grn_number(): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload());
        $receipt = GoodsReceipt::query()->sole();

        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.post', $receipt))
            ->assertRedirect(route('goods-receipts.show', $receipt));

        $receipt->refresh();
        $this->assertSame(GoodsReceiptStatus::Posted, $receipt->status);
        $this->assertSame('GRN-'.now()->format('Y').'-00001', $receipt->number);
        $this->assertSame($this->inventoryOfficer->id, $receipt->posted_by);
        $this->assertSame('20.000', app(StockService::class)->available($this->cement, $this->warehouse));
        $this->assertSame(2, $receipt->stockMovements()->where('type', StockMovementType::GoodsReceived->value)->count());
    }

    public function test_save_and_post_does_both_in_one_step(): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload(['post_now' => '1']));

        $this->assertSame(GoodsReceiptStatus::Posted, GoodsReceipt::query()->sole()->status);
        $this->assertSame('2.500', app(StockService::class)->available($this->nails, $this->warehouse));
    }

    public function test_validation_rejects_missing_lines_duplicates_service_items_and_bad_quantities(): void
    {
        $service = Item::factory()->service()->create();

        $this->actingAs($this->inventoryOfficer)
            ->post(route('goods-receipts.store'), $this->payload(['lines' => []]))
            ->assertSessionHasErrors(['lines' => 'Add at least one item.']);

        $this->actingAs($this->inventoryOfficer)
            ->post(route('goods-receipts.store'), $this->payload(['lines' => [
                ['item_id' => $this->cement->id, 'quantity' => '1', 'unit_cost' => '1'],
                ['item_id' => $this->cement->id, 'quantity' => '1', 'unit_cost' => '1'],
                ['item_id' => $service->id, 'quantity' => '0', 'unit_cost' => '-5'],
            ]]))
            ->assertSessionHasErrors([
                'lines.0.item_id' => 'Each item can only appear once.',
                'lines.2.item_id' => 'Choose an active stock item.',
                'lines.2.quantity',
                'lines.2.unit_cost',
            ]);

        $this->assertSame(0, GoodsReceipt::query()->count());
    }

    public function test_posted_notes_cannot_be_edited_or_deleted(): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload(['post_now' => '1']));
        $receipt = GoodsReceipt::query()->sole();

        $this->actingAs($this->inventoryOfficer)->put(route('goods-receipts.update', $receipt), $this->payload())->assertForbidden();
        $this->actingAs($this->inventoryOfficer)->delete(route('goods-receipts.destroy', $receipt))->assertForbidden();
    }

    public function test_drafts_can_be_edited_and_deleted(): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload());
        $receipt = GoodsReceipt::query()->sole();

        $this->actingAs($this->inventoryOfficer)->put(route('goods-receipts.update', $receipt), $this->payload(['lines' => [
            ['item_id' => $this->nails->id, 'quantity' => '1', 'unit_cost' => '7000'],
        ]]))->assertRedirect(route('goods-receipts.show', $receipt));
        $this->assertSame('7000.00', (string) $receipt->fresh()->total);

        $this->actingAs($this->inventoryOfficer)->delete(route('goods-receipts.destroy', $receipt))->assertRedirect(route('goods-receipts.index'));
        $this->assertSame(0, GoodsReceipt::query()->count());
    }

    public function test_a_manager_voids_a_posted_note_and_its_stock_is_reversed(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload(['post_now' => '1']));
        $receipt = GoodsReceipt::query()->sole();

        $this->actingAs($manager)->post(route('goods-receipts.void', $receipt), ['reason' => 'Delivered to wrong branch'])
            ->assertRedirect(route('goods-receipts.show', $receipt));

        $receipt->refresh();
        $this->assertSame(GoodsReceiptStatus::Void, $receipt->status);
        $this->assertSame('Delivered to wrong branch', $receipt->void_reason);
        $this->assertSame('0.000', app(StockService::class)->available($this->cement, $this->warehouse));
    }

    public function test_the_person_who_received_goods_cannot_void_them(): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload(['post_now' => '1']));

        $this->actingAs($this->inventoryOfficer)
            ->post(route('goods-receipts.void', GoodsReceipt::query()->sole()), ['reason' => 'Mistake in quantities'])
            ->assertForbidden();
    }

    public function test_voiding_fails_with_a_message_when_the_stock_was_already_issued(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload(['post_now' => '1']));
        $receipt = GoodsReceipt::query()->sole();
        app(StockService::class)->issue($this->cement, $this->warehouse, '15');

        $this->actingAs($manager)->from(route('goods-receipts.show', $receipt))
            ->post(route('goods-receipts.void', $receipt), ['reason' => 'Supplier took goods back'])
            ->assertRedirect(route('goods-receipts.show', $receipt))
            ->assertSessionHas('error');

        $this->assertSame(GoodsReceiptStatus::Posted, $receipt->fresh()->status);
        $this->assertSame('5.000', app(StockService::class)->available($this->cement, $this->warehouse));
    }

    public function test_users_without_receive_permission_cannot_create_notes(): void
    {
        $accountant = $this->userWithRole(SystemRole::Accountant);

        $this->actingAs($accountant)->post(route('goods-receipts.store'), $this->payload())->assertForbidden();
    }

    public function test_pages_render(): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload());
        $receipt = GoodsReceipt::query()->sole();

        $this->actingAs($this->inventoryOfficer)->get(route('goods-receipts.index'))->assertOk()->assertSee($this->supplier->name);
        $this->actingAs($this->inventoryOfficer)->get(route('goods-receipts.create'))->assertOk();
        $this->actingAs($this->inventoryOfficer)->get(route('goods-receipts.edit', $receipt))->assertOk()->assertSee('Cement 50kg');
        $this->actingAs($this->inventoryOfficer)->get(route('goods-receipts.show', $receipt))->assertOk()->assertSee('DN-4471');
    }

    public function test_a_supplier_with_goods_received_cannot_be_deleted(): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), $this->payload());
        $procurement = $this->userWithRole(SystemRole::ProcurementOfficer);

        $this->actingAs($procurement)->from(route('suppliers.show', $this->supplier))
            ->delete(route('suppliers.destroy', $this->supplier))
            ->assertSessionHas('error', 'This supplier has transactions and cannot be deleted. Deactivate it instead.');

        $this->assertNotSoftDeleted($this->supplier);
        $this->actingAs($procurement)->get(route('suppliers.show', $this->supplier))->assertOk()->assertSee('Draft #');
    }
}
