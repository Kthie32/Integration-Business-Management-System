<?php

namespace Tests\Feature\Procurement;

use App\Enums\ExpenseStatus;
use App\Enums\FulfilmentType;
use App\Enums\RequisitionStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SystemMessage;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesRequisitions;
use Tests\TestCase;

class RequisitionFulfilmentTest extends TestCase
{
    use CreatesRequisitions, RefreshDatabase;

    private Department $department;

    private User $requester;

    private User $manager;

    private User $storekeeper;

    private Warehouse $warehouse;

    private Item $stockItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->department = Department::factory()->create();
        $this->requester = $this->userWithRole(SystemRole::GeneralEmployee);
        $this->manager = $this->userWithRole(SystemRole::Manager);
        $this->storekeeper = $this->userWithRole(SystemRole::InventoryOfficer);
        $this->warehouse = Warehouse::factory()->default()->create();
        $this->stockItem = Item::factory()->create(['name' => 'Printer paper', 'unit' => 'ream']);
        app(StockService::class)->receive($this->stockItem, $this->warehouse, '50', '20000');
    }

    /**
     * Without a workflow the engine uses one "any authorised approver" step, so a Manager's approval completes it.
     */
    private function approvedRequisition(): Requisition
    {
        $requisition = $this->submittedRequisition($this->requester, $this->department, [
            ['item_id' => (string) $this->stockItem->id, 'quantity' => '10'],
            ['description' => 'Chair repair (workshop)', 'quantity' => '2', 'estimated_unit_cost' => '45000'],
        ]);

        return $this->decide($requisition, $this->manager);
    }

    private function lineId(Requisition $requisition, int $position): int
    {
        return $requisition->items()->get()[$position]->id;
    }

    public function test_stock_lines_are_issued_from_the_warehouse_and_a_partial_delivery_is_recorded(): void
    {
        $requisition = $this->approvedRequisition();
        $this->assertSame(RequisitionStatus::Approved, $requisition->status);

        $this->actingAs($this->storekeeper)->get(route('requisitions.fulfil', $requisition))->assertOk()->assertSee('Printer paper');

        $this->actingAs($this->storekeeper)->post(route('requisitions.fulfil.store', $requisition), [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [$this->lineId($requisition, 0) => ['quantity' => '6']],
        ])->assertSessionHasNoErrors()->assertRedirect(route('requisitions.show', $requisition));

        $requisition->refresh();
        $this->assertSame(RequisitionStatus::PartiallyFulfilled, $requisition->status);
        $this->assertSame('44.000', app(StockService::class)->available($this->stockItem, $this->warehouse));

        $fulfilment = $requisition->fulfilments()->sole();
        $this->assertSame(FulfilmentType::StockIssue, $fulfilment->fulfilment_type);
        $this->assertSame(StockMovementType::Issue, $fulfilment->stockMovement->type);
        $this->assertTrue($fulfilment->stockMovement->reference->is($fulfilment));
        $this->assertSame('120000.00', (string) $fulfilment->amount);
        $this->assertSame('6.000', (string) $requisition->items()->first()->fulfilled_quantity);

        $this->actingAs($this->storekeeper)->get(route('stock.movements'))->assertOk()->assertSee($requisition->number);
    }

    public function test_purchased_lines_create_one_draft_expense_linked_to_the_requisition(): void
    {
        Notification::fake();
        $category = ExpenseCategory::factory()->create();
        $supplier = Supplier::factory()->create();
        $finance = $this->userWithRole(SystemRole::FinanceOfficer);
        $procurement = $this->userWithRole(SystemRole::ProcurementOfficer);
        $requisition = $this->approvedRequisition();

        $this->actingAs($procurement)->post(route('requisitions.fulfil.store', $requisition), [
            'expense_category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'notes' => 'Workshop invoice 778',
            'lines' => [$this->lineId($requisition, 1) => ['quantity' => '2', 'unit_cost' => '50000']],
        ])->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertSame(ExpenseStatus::Draft, $expense->status);
        $this->assertSame('100000.00', (string) $expense->amount);
        $this->assertSame($this->department->id, $expense->department_id);
        $this->assertSame($supplier->id, $expense->supplier_id);
        $this->assertTrue($expense->source->is($requisition));
        $this->assertStringContainsString($requisition->number, $expense->description);
        $this->assertSame($expense->id, $requisition->fulfilments()->sole()->expense_id);
        $this->assertSame(RequisitionStatus::PartiallyFulfilled, $requisition->fresh()->status);
        Notification::assertSentTo($finance, SystemMessage::class, fn (SystemMessage $message): bool => str_contains($message->title, 'Draft expense'));

        $this->actingAs($finance)->get(route('expenses.show', $expense))->assertOk()->assertSee($requisition->number);
    }

    public function test_delivering_every_approved_quantity_fulfils_the_requisition(): void
    {
        $category = ExpenseCategory::factory()->create();
        $requisition = $this->approvedRequisition();

        $this->actingAs($this->storekeeper)->post(route('requisitions.fulfil.store', $requisition), [
            'warehouse_id' => $this->warehouse->id,
            'expense_category_id' => $category->id,
            'lines' => [
                $this->lineId($requisition, 0) => ['quantity' => '10'],
                $this->lineId($requisition, 1) => ['quantity' => '2', 'unit_cost' => '45000'],
            ],
        ])->assertSessionHasNoErrors();

        $requisition->refresh();
        $this->assertSame(RequisitionStatus::Fulfilled, $requisition->status);
        $this->assertNotNull($requisition->fulfilled_at);
        $this->assertSame(2, $requisition->fulfilments()->count());

        $this->actingAs($this->storekeeper)->get(route('requisitions.fulfil', $requisition))->assertForbidden();
    }

    public function test_more_than_the_approved_quantity_cannot_be_delivered(): void
    {
        $requisition = $this->approvedRequisition();

        $this->actingAs($this->storekeeper)->post(route('requisitions.fulfil.store', $requisition), [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [$this->lineId($requisition, 0) => ['quantity' => '11']],
        ])->assertSessionHas('error');

        $this->assertSame(0, $requisition->fulfilments()->count());
        $this->assertSame('50.000', app(StockService::class)->available($this->stockItem, $this->warehouse));
    }

    public function test_a_stock_shortage_rolls_back_the_whole_delivery(): void
    {
        $category = ExpenseCategory::factory()->create();
        $requisition = $this->approvedRequisition();
        $emptyStore = Warehouse::factory()->create();

        $this->actingAs($this->storekeeper)->post(route('requisitions.fulfil.store', $requisition), [
            'warehouse_id' => $emptyStore->id,
            'expense_category_id' => $category->id,
            'lines' => [
                $this->lineId($requisition, 0) => ['quantity' => '5'],
                $this->lineId($requisition, 1) => ['quantity' => '1', 'unit_cost' => '45000'],
            ],
        ])->assertSessionHas('error');

        $this->assertSame(0, $requisition->fulfilments()->count());
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame(RequisitionStatus::Approved, $requisition->fresh()->status);
        $this->assertSame('0.000', (string) $requisition->items()->first()->fulfilled_quantity);
    }

    public function test_purchases_need_an_expense_category_and_some_quantity_must_be_entered(): void
    {
        $requisition = $this->approvedRequisition();

        $this->actingAs($this->storekeeper)->post(route('requisitions.fulfil.store', $requisition), [
            'lines' => [$this->lineId($requisition, 1) => ['quantity' => '1', 'unit_cost' => '45000']],
        ])->assertSessionHas('error', 'Choose an active expense category for the purchased lines.');

        $this->actingAs($this->storekeeper)->post(route('requisitions.fulfil.store', $requisition), [
            'lines' => [$this->lineId($requisition, 0) => ['quantity' => '']],
        ])->assertSessionHas('error', 'Enter the quantity delivered for at least one line.');
    }

    public function test_only_fulfillers_deliver_and_only_approved_requisitions(): void
    {
        $pending = $this->submittedRequisition($this->requester, $this->department);
        $requisition = $this->approvedRequisition();

        $this->actingAs($this->storekeeper)->get(route('requisitions.fulfil', $pending))->assertForbidden();
        $this->actingAs($this->requester)->get(route('requisitions.fulfil', $requisition))->assertForbidden();
        $this->actingAs($this->manager)->post(route('requisitions.fulfil.store', $requisition), [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [$this->lineId($requisition, 0) => ['quantity' => '1']],
        ])->assertForbidden();

        $this->actingAs($this->userWithRole(SystemRole::ProcurementOfficer))->get(route('requisitions.fulfil', $requisition))->assertOk();
    }
}
