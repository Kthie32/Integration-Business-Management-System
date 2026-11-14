<?php

namespace Tests\Feature\Integration;

use App\Enums\ExpenseStatus;
use App\Enums\RequisitionStatus;
use App\Enums\SystemRole;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use Database\Seeders\ApprovalWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRequisitions;
use Tests\TestCase;

/**
 * A requisition from request to delivery: the standard three-step approval route, stock issued from the store,
 * purchased lines turned into an expense that Finance records, and the result in the requisition and expense reports.
 */
class RequisitionToFulfilmentTest extends TestCase
{
    use CreatesRequisitions, RefreshDatabase;

    private Department $department;

    private User $departmentHead;

    private User $requester;

    private User $financeOfficer;

    private User $manager;

    private Warehouse $warehouse;

    private Item $paper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ApprovalWorkflowSeeder::class);
        [$this->department, $this->departmentHead] = $this->departmentWithHead();
        $this->requester = $this->userWithRole(SystemRole::GeneralEmployee);
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->manager = $this->userWithRole(SystemRole::Manager);
        $this->warehouse = Warehouse::factory()->default()->create();
        $this->paper = Item::factory()->create(['name' => 'Printer paper', 'unit' => 'ream']);
        app(StockService::class)->receive($this->paper, $this->warehouse, '40', '20000');
    }

    public function test_a_requisition_is_approved_in_order_fulfilled_from_stock_and_purchase_and_reported(): void
    {
        $requisition = $this->employeeSubmitsARequisition();
        $this->assertSame(RequisitionStatus::Submitted, $requisition->status);
        $this->assertNotNull($requisition->number);

        $this->actingAs($this->manager)->post(route('requisitions.approve', $requisition))->assertForbidden();
        $this->actingAs($this->requester)->post(route('requisitions.approve', $requisition))->assertForbidden();
        $this->approve($requisition, $this->departmentHead);
        $this->actingAs($this->financeOfficer)->get(route('approvals.inbox'))->assertOk()->assertSee($requisition->number);
        $this->approve($requisition, $this->financeOfficer);
        $this->approve($requisition, $this->manager);
        $this->assertSame(RequisitionStatus::Approved, $requisition->refresh()->status);
        $this->assertGreaterThan(0, $this->requester->notifications()->count());

        $this->actingAs($this->userWithRole(SystemRole::InventoryOfficer))->post(route('requisitions.fulfil.store', $requisition), [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [$this->line($requisition, 0) => ['quantity' => '6']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('34.000', app(StockService::class)->available($this->paper, $this->warehouse));
        $this->assertSame(RequisitionStatus::PartiallyFulfilled, $requisition->refresh()->status);

        $this->actingAs($this->userWithRole(SystemRole::ProcurementOfficer))->post(route('requisitions.fulfil.store', $requisition), [
            'expense_category_id' => ExpenseCategory::factory()->create()->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'lines' => [$this->line($requisition, 1) => ['quantity' => '2', 'unit_cost' => '45000']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(RequisitionStatus::Fulfilled, $requisition->refresh()->status);
        $expense = Expense::query()->sole();
        $this->assertSame('90000.00', (string) $expense->amount);

        $this->actingAs($this->financeOfficer)->put(route('expenses.update', $expense), [
            'expense_date' => today()->toDateString(),
            'expense_category_id' => $expense->expense_category_id,
            'supplier_id' => $expense->supplier_id,
            'department_id' => $expense->department_id,
            'description' => $expense->description,
            'amount' => '90000',
            'payment_method' => 'bank_transfer',
            'reference' => 'TT-9001',
            'action' => 'record',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(ExpenseStatus::Recorded, $expense->refresh()->status);

        $report = $this->actingAs($this->manager)->get(route('reports.show', 'requisitions'));
        $report->assertOk();
        $report->assertSee($requisition->number);
        $expenses = $this->actingAs($this->financeOfficer)->get(route('reports.show', 'expenses'));
        $expenses->assertOk();
        $expenses->assertSee($expense->number);
    }

    public function test_a_rejection_at_the_first_step_ends_the_route_and_nothing_can_be_fulfilled(): void
    {
        $requisition = $this->employeeSubmitsARequisition();

        $this->actingAs($this->departmentHead)->post(route('requisitions.reject', $requisition), ['comment' => 'Use the stock in the field office'])
            ->assertSessionHasNoErrors();

        $this->assertSame(RequisitionStatus::Rejected, $requisition->refresh()->status);
        $this->actingAs($this->financeOfficer)->post(route('requisitions.approve', $requisition))->assertForbidden();
        $this->actingAs($this->userWithRole(SystemRole::InventoryOfficer))->get(route('requisitions.fulfil', $requisition))->assertForbidden();
        $this->assertSame('40.000', app(StockService::class)->available($this->paper, $this->warehouse));
    }

    private function employeeSubmitsARequisition(): Requisition
    {
        $this->actingAs($this->requester)->post(route('requisitions.store'), [
            'department_id' => $this->department->id,
            'priority' => 'normal',
            'purpose' => 'Supplies for the quarterly field visit',
            'lines' => [
                ['item_id' => $this->paper->id, 'quantity' => '6'],
                ['description' => 'Tarpaulin 4x6m', 'quantity' => '2', 'estimated_unit_cost' => '45000'],
            ],
            'action' => 'submit',
        ])->assertSessionHasNoErrors();

        return Requisition::query()->where('requested_by', $this->requester->id)->sole();
    }

    private function approve(Requisition $requisition, User $approver): void
    {
        $this->actingAs($approver)->post(route('requisitions.approve', $requisition))->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    private function line(Requisition $requisition, int $position): int
    {
        return $requisition->items()->orderBy('id')->get()[$position]->id;
    }
}
