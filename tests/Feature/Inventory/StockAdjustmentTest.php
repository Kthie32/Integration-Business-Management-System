<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockAdjustmentStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SystemMessage;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $inventoryOfficer;

    private User $manager;

    private Warehouse $warehouse;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->inventoryOfficer = $this->userWithRole(SystemRole::InventoryOfficer);
        $this->manager = $this->userWithRole(SystemRole::Manager);
        $this->warehouse = Warehouse::factory()->default()->create();
        $this->item = Item::factory()->create();
        app(StockService::class)->receive($this->item, $this->warehouse, '10', '1000');
    }

    /**
     * @param  list<array<string, string>>  $lines
     */
    private function raise(array $lines): StockAdjustment
    {
        $this->actingAs($this->inventoryOfficer)->post(route('stock-adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'adjusted_at' => now()->toDateString(),
            'reason' => 'Year-end stock count',
            'lines' => $lines,
        ])->assertSessionHasNoErrors();

        return StockAdjustment::query()->latest('id')->firstOrFail();
    }

    private function available(): string
    {
        return app(StockService::class)->available($this->item, $this->warehouse);
    }

    public function test_raising_an_adjustment_does_not_change_stock_and_notifies_approvers(): void
    {
        Notification::fake();

        $adjustment = $this->raise([['item_id' => (string) $this->item->id, 'quantity_change' => '-2']]);

        $this->assertSame(StockAdjustmentStatus::Pending, $adjustment->status);
        $this->assertSame('ADJ-'.now()->format('Y').'-00001', $adjustment->number);
        $this->assertSame('10.000', $this->available());
        Notification::assertSentTo($this->manager, SystemMessage::class);
        Notification::assertNotSentTo($this->inventoryOfficer, SystemMessage::class);
    }

    public function test_approval_by_another_person_applies_increases_and_decreases(): void
    {
        $other = Item::factory()->create();
        $adjustment = $this->raise([
            ['item_id' => (string) $this->item->id, 'quantity_change' => '-2.5'],
            ['item_id' => (string) $other->id, 'quantity_change' => '4', 'unit_cost' => '750'],
        ]);

        $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))
            ->assertRedirect(route('stock-adjustments.show', $adjustment));

        $adjustment->refresh();
        $this->assertSame(StockAdjustmentStatus::Approved, $adjustment->status);
        $this->assertSame($this->manager->id, $adjustment->approved_by);
        $this->assertSame('7.500', $this->available());
        $this->assertSame('4.000', app(StockService::class)->available($other, $this->warehouse));
        $this->assertSame(
            [StockMovementType::AdjustmentOut, StockMovementType::AdjustmentIn],
            $adjustment->stockMovements()->orderBy('id')->get()->pluck('type')->all(),
        );
    }

    public function test_an_increase_without_a_unit_cost_uses_the_current_average_cost(): void
    {
        $adjustment = $this->raise([['item_id' => (string) $this->item->id, 'quantity_change' => '5']]);

        $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment));

        $this->assertSame('1000.0000', (string) $adjustment->stockMovements()->sole()->unit_cost);
    }

    public function test_the_creator_cannot_approve_their_own_adjustment_even_as_super_administrator(): void
    {
        $superAdministrator = $this->userWithRole(SystemRole::SuperAdministrator);
        $this->actingAs($superAdministrator)->post(route('stock-adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'adjusted_at' => now()->toDateString(),
            'reason' => 'Found extra cartons',
            'lines' => [['item_id' => (string) $this->item->id, 'quantity_change' => '1']],
        ]);
        $adjustment = StockAdjustment::query()->sole();

        $this->actingAs($superAdministrator)->post(route('stock-adjustments.approve', $adjustment))->assertForbidden();
        $this->assertSame('10.000', $this->available());
    }

    public function test_users_without_approve_permission_cannot_decide(): void
    {
        $adjustment = $this->raise([['item_id' => (string) $this->item->id, 'quantity_change' => '-1']]);
        $procurement = $this->userWithRole(SystemRole::ProcurementOfficer);

        $this->actingAs($procurement)->post(route('stock-adjustments.approve', $adjustment))->assertForbidden();
    }

    public function test_approving_a_decrease_larger_than_the_stock_fails_and_leaves_it_pending(): void
    {
        $adjustment = $this->raise([['item_id' => (string) $this->item->id, 'quantity_change' => '-4']]);
        app(StockService::class)->issue($this->item, $this->warehouse, '8');

        $this->actingAs($this->manager)->from(route('stock-adjustments.show', $adjustment))
            ->post(route('stock-adjustments.approve', $adjustment))
            ->assertSessionHas('error');

        $this->assertSame(StockAdjustmentStatus::Pending, $adjustment->fresh()->status);
        $this->assertSame('2.000', $this->available());
    }

    public function test_rejection_needs_a_reason_and_leaves_stock_unchanged(): void
    {
        $adjustment = $this->raise([['item_id' => (string) $this->item->id, 'quantity_change' => '-1']]);

        $this->actingAs($this->manager)->post(route('stock-adjustments.reject', $adjustment), ['comment' => ''])
            ->assertSessionHasErrors('comment');

        $this->actingAs($this->manager)->post(route('stock-adjustments.reject', $adjustment), ['comment' => 'Recount first'])
            ->assertRedirect(route('stock-adjustments.show', $adjustment));

        $this->assertSame(StockAdjustmentStatus::Rejected, $adjustment->fresh()->status);
        $this->assertSame('Recount first', $adjustment->statusHistories()->first()->comment);
        $this->assertSame('10.000', $this->available());
    }

    public function test_only_the_creator_can_cancel_and_decided_adjustments_cannot_be_approved_again(): void
    {
        $adjustment = $this->raise([['item_id' => (string) $this->item->id, 'quantity_change' => '-1']]);

        $this->actingAs($this->manager)->post(route('stock-adjustments.cancel', $adjustment))->assertForbidden();
        $this->actingAs($this->inventoryOfficer)->post(route('stock-adjustments.cancel', $adjustment))->assertRedirect();
        $this->assertSame(StockAdjustmentStatus::Cancelled, $adjustment->fresh()->status);

        $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))->assertForbidden();
    }

    public function test_validation_rejects_zero_changes_and_a_missing_reason(): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('stock-adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'adjusted_at' => now()->toDateString(),
            'reason' => '',
            'lines' => [['item_id' => (string) $this->item->id, 'quantity_change' => '0']],
        ])->assertSessionHasErrors(['reason', 'lines.0.quantity_change' => 'The change cannot be zero.']);

        $this->assertSame(0, StockAdjustment::query()->count());
    }

    public function test_pages_render(): void
    {
        $adjustment = $this->raise([['item_id' => (string) $this->item->id, 'quantity_change' => '-1']]);

        $this->actingAs($this->inventoryOfficer)->get(route('stock-adjustments.index'))->assertOk()->assertSee($adjustment->number);
        $this->actingAs($this->inventoryOfficer)->get(route('stock-adjustments.create'))->assertOk();
        $this->actingAs($this->inventoryOfficer)->get(route('stock-adjustments.show', $adjustment))->assertOk()->assertDontSee('Your decision');
        $this->actingAs($this->manager)->get(route('stock-adjustments.show', $adjustment))->assertOk()->assertSee('Your decision');
    }
}
