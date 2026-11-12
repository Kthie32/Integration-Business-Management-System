<?php

namespace Tests\Feature\Approvals;

use App\Enums\ApproverType;
use App\Enums\SystemRole;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Item;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Approval\ApprovalInbox;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockService;
use Database\Seeders\ApprovalWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesRequisitions;
use Tests\TestCase;

class ApprovalWorkflowAndInboxTest extends TestCase
{
    use CreatesRequisitions, RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ApprovalWorkflowSeeder::class);
        $this->administrator = $this->userWithRole(SystemRole::SuperAdministrator);
    }

    public function test_workflows_are_listed_and_created_with_ordered_steps(): void
    {
        $finance = Role::findByName(SystemRole::FinanceOfficer->value);
        $director = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($this->administrator)->get(route('approval-workflows.index'))->assertOk()->assertSee('Standard requisition approval');
        $this->actingAs($this->administrator)->get(route('approval-workflows.create'))->assertOk();

        $this->actingAs($this->administrator)->post(route('approval-workflows.store'), [
            'document_type' => 'requisition',
            'name' => 'Capital purchases',
            'min_amount' => '10000000',
            'is_active' => '1',
            'steps' => [
                ['name' => 'Finance', 'approver_type' => 'role', 'role_id' => (string) $finance->id, 'min_amount' => ''],
                ['name' => '', 'approver_type' => '', 'role_id' => '', 'user_id' => ''],
                ['name' => 'Director', 'approver_type' => 'user', 'user_id' => (string) $director->id, 'min_amount' => '20000000'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('approval-workflows.index'));

        $workflow = ApprovalWorkflow::query()->where('name', 'Capital purchases')->with('steps')->sole();
        $this->assertSame('10000000.00', (string) $workflow->min_amount);
        $this->assertSame([1, 2], $workflow->steps->pluck('sequence')->all());
        $this->assertSame([ApproverType::Role, ApproverType::User], $workflow->steps->pluck('approver_type')->all());
        $this->assertSame($director->id, $workflow->steps[1]->user_id);
        $this->assertNull($workflow->steps[1]->role_id);
        $this->assertSame('20000000.00', (string) $workflow->steps[1]->min_amount);
    }

    public function test_role_and_user_steps_need_their_approver_and_a_workflow_needs_a_step(): void
    {
        $this->actingAs($this->administrator)->post(route('approval-workflows.store'), [
            'document_type' => 'requisition',
            'name' => 'Broken',
            'steps' => [
                ['approver_type' => 'role', 'role_id' => ''],
                ['approver_type' => 'user', 'user_id' => ''],
            ],
        ])->assertSessionHasErrors(['steps.0.role_id', 'steps.1.user_id']);

        $this->actingAs($this->administrator)->post(route('approval-workflows.store'), [
            'document_type' => 'requisition',
            'name' => 'Empty',
            'steps' => [],
        ])->assertSessionHasErrors('steps');

        $this->assertSame(1, ApprovalWorkflow::query()->count());
    }

    public function test_editing_replaces_the_steps_and_deleting_keeps_requests_in_progress(): void
    {
        $workflow = ApprovalWorkflow::query()->sole();
        [$department, $head] = $this->departmentWithHead();
        $requisition = $this->submittedRequisition($this->userWithRole(SystemRole::GeneralEmployee), $department);

        $this->actingAs($this->administrator)->get(route('approval-workflows.edit', $workflow))->assertOk()->assertSee('Department head');
        $this->actingAs($this->administrator)->put(route('approval-workflows.update', $workflow), [
            'document_type' => 'requisition',
            'name' => 'Head only',
            'is_active' => '0',
            'steps' => [['approver_type' => 'department_head']],
        ])->assertSessionHasNoErrors();

        $workflow->refresh();
        $this->assertFalse($workflow->is_active);
        $this->assertSame(1, $workflow->steps()->count());

        $this->actingAs($this->administrator)->delete(route('approval-workflows.destroy', $workflow))->assertRedirect(route('approval-workflows.index'));

        $this->assertModelMissing($workflow);
        $this->assertSame(3, $requisition->approvals()->count());
        $this->assertNull($requisition->approvals()->first()->approval_workflow_id);
        $this->actingAs($head)->post(route('requisitions.approve', $requisition))->assertRedirect();
    }

    public function test_only_holders_of_approvals_configure_manage_workflows(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);
        $workflow = ApprovalWorkflow::query()->sole();

        $this->actingAs($manager)->get(route('approval-workflows.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('approval-workflows.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($manager)->delete(route('approval-workflows.destroy', $workflow))->assertForbidden();

        $manager->roles()->first()->givePermissionTo('approvals.configure');
        $this->actingAs($manager->fresh())->get(route('approval-workflows.index'))->assertOk();
    }

    public function test_the_inbox_lists_requisition_steps_and_stock_adjustments_awaiting_the_user(): void
    {
        [$department, $head] = $this->departmentWithHead(SystemRole::Manager);
        $requester = $this->userWithRole(SystemRole::GeneralEmployee);
        $requisition = $this->submittedRequisition($requester, $department);
        $finance = $this->userWithRole(SystemRole::FinanceOfficer);

        $warehouse = Warehouse::factory()->default()->create();
        $item = Item::factory()->create();
        app(StockService::class)->receive($item, $warehouse, '5', '100');
        $adjustment = app(StockAdjustmentService::class)->create([
            'warehouse_id' => $warehouse->id,
            'adjusted_at' => today()->toDateString(),
            'reason' => 'Count difference in aisle 3',
            'lines' => [['item_id' => $item->id, 'quantity_change' => '-1']],
        ], $this->userWithRole(SystemRole::InventoryOfficer));

        $this->actingAs($head)->get(route('approvals.inbox'))
            ->assertOk()
            ->assertSee($requisition->number)
            ->assertSee('Department head')
            ->assertSee($adjustment->number);

        $this->assertSame(0, app(ApprovalInbox::class)->countFor($finance));
        $this->actingAs($requester)->get(route('approvals.inbox'))->assertOk()->assertSee('Nothing is waiting for your approval.');

        $this->decide($requisition, $head);
        $this->actingAs($finance)->get(route('approvals.inbox'))->assertOk()->assertSee($requisition->number);
        $this->assertSame([$adjustment->number], app(ApprovalInbox::class)->itemsFor($head)->pluck('number')->all(), 'Notifications in the top bar may still mention the requisition, so check the inbox itself.');
    }

    /**
     * @return array<string, array{0: SystemRole, 1: array{viewAny: bool, create: bool, viewAll: bool, approve: bool, fulfil: bool, configure: bool}}>
     */
    public static function roleMatrix(): array
    {
        $base = ['viewAny' => true, 'create' => true, 'viewAll' => false, 'approve' => false, 'fulfil' => false, 'configure' => false];

        return [
            'super administrator' => [SystemRole::SuperAdministrator, ['viewAny' => true, 'create' => true, 'viewAll' => true, 'approve' => true, 'fulfil' => true, 'configure' => true]],
            'system administrator' => [SystemRole::SystemAdministrator, $base],
            'manager' => [SystemRole::Manager, [...$base, 'viewAll' => true, 'approve' => true]],
            'finance officer' => [SystemRole::FinanceOfficer, [...$base, 'viewAll' => true, 'approve' => true]],
            'accountant' => [SystemRole::Accountant, [...$base, 'viewAll' => true]],
            'human resource officer' => [SystemRole::HumanResourceOfficer, $base],
            'procurement officer' => [SystemRole::ProcurementOfficer, [...$base, 'viewAll' => true, 'fulfil' => true]],
            'inventory officer' => [SystemRole::InventoryOfficer, [...$base, 'viewAll' => true, 'fulfil' => true]],
            'department head' => [SystemRole::DepartmentHead, [...$base, 'approve' => true]],
            'general employee' => [SystemRole::GeneralEmployee, $base],
        ];
    }

    /**
     * @param  array{viewAny: bool, create: bool, viewAll: bool, approve: bool, fulfil: bool, configure: bool}  $expected
     */
    #[DataProvider('roleMatrix')]
    public function test_role_matrix(SystemRole $role, array $expected): void
    {
        $user = $this->userWithRole($role);
        $someoneElses = Requisition::factory()->create(['department_id' => Department::factory()]);

        $this->assertSame($expected, [
            'viewAny' => $user->can('viewAny', Requisition::class),
            'create' => $user->can('create', Requisition::class),
            'viewAll' => $user->can('view', $someoneElses),
            'approve' => $user->can('requisitions.approve'),
            'fulfil' => $user->can('requisitions.fulfil'),
            'configure' => $user->can('viewAny', ApprovalWorkflow::class),
        ]);
    }
}
