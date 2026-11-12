<?php

namespace Tests\Feature\Approvals;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStatus;
use App\Enums\ApproverType;
use App\Enums\RequisitionStatus;
use App\Enums\SystemRole;
use App\Events\DocumentApproved;
use App\Exceptions\BusinessRuleException;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SystemMessage;
use App\Services\Approval\ApprovalService;
use App\Services\Requisition\RequisitionService;
use Database\Seeders\ApprovalWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesRequisitions;
use Tests\TestCase;

class ApprovalEngineTest extends TestCase
{
    use CreatesRequisitions, RefreshDatabase;

    private Department $department;

    private User $head;

    private User $requester;

    private User $finance;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ApprovalWorkflowSeeder::class);

        [$this->department, $this->head] = $this->departmentWithHead();
        $this->requester = $this->userWithRole(SystemRole::GeneralEmployee);
        $this->finance = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->manager = $this->userWithRole(SystemRole::Manager);
    }

    /**
     * @return list<string>
     */
    private function trail(Requisition $requisition): array
    {
        return $requisition->approvals()->get()->map(fn ($step): string => "{$step->round}.{$step->sequence} {$step->name}: {$step->status->value}")->all();
    }

    public function test_the_seeded_default_route_is_department_head_then_finance_then_manager(): void
    {
        $workflow = ApprovalWorkflow::query()->with('steps.role')->sole();

        $this->assertSame(
            [ApproverType::DepartmentHead, ApproverType::Role, ApproverType::Role],
            $workflow->steps->pluck('approver_type')->all(),
        );
        $this->assertSame([SystemRole::FinanceOfficer->value, SystemRole::Manager->value], $workflow->steps->skip(1)->map(fn ($step) => $step->role->name)->values()->all());

        $this->seed(ApprovalWorkflowSeeder::class);
        $this->assertSame(1, ApprovalWorkflow::query()->count());
    }

    public function test_a_requisition_moves_through_every_step_in_order_and_is_approved(): void
    {
        Event::fake([DocumentApproved::class]);
        $requisition = $this->submittedRequisition($this->requester, $this->department);

        $this->assertSame(RequisitionStatus::Submitted, $requisition->status);
        $this->assertSame('REQ-'.now()->format('Y').'-00001', $requisition->number);
        $this->assertFalse(app(ApprovalService::class)->canAct($this->finance, $requisition));
        $this->assertTrue(app(ApprovalService::class)->canAct($this->head, $requisition));

        $requisition = $this->decide($requisition, $this->head);
        $this->assertSame(RequisitionStatus::InApproval, $requisition->status);

        $requisition = $this->decide($requisition, $this->finance, ApprovalDecision::Approve, 'Within budget');
        $this->assertSame(RequisitionStatus::InApproval, $requisition->status);
        Event::assertNotDispatched(DocumentApproved::class);

        $requisition = $this->decide($requisition, $this->manager);

        $this->assertSame(RequisitionStatus::Approved, $requisition->status);
        $this->assertNotNull($requisition->approved_at);
        $this->assertSame('10.000', (string) $requisition->items()->sole()->approved_quantity);
        $this->assertSame([
            '1.1 Department head: approved',
            '1.2 Finance: approved',
            '1.3 Management: approved',
        ], $this->trail($requisition));
        $this->assertSame([$this->head->id, $this->finance->id, $this->manager->id], $requisition->approvals()->pluck('decided_by')->all());
        Event::assertDispatched(DocumentApproved::class, fn (DocumentApproved $event): bool => $event->document->is($requisition));
    }

    public function test_only_the_current_steps_approvers_are_notified_and_the_requester_and_fulfillers_hear_of_approval(): void
    {
        Notification::fake();
        $inventory = $this->userWithRole(SystemRole::InventoryOfficer);

        $requisition = $this->submittedRequisition($this->requester, $this->department);

        Notification::assertSentTo($this->head, SystemMessage::class);
        Notification::assertNotSentTo([$this->finance, $this->manager, $this->requester], SystemMessage::class);

        $requisition = $this->decide($requisition, $this->head);
        Notification::assertSentTo($this->finance, SystemMessage::class);

        $requisition = $this->decide($requisition, $this->finance);
        $this->decide($requisition, $this->manager);

        Notification::assertSentTo($this->requester, SystemMessage::class, fn (SystemMessage $message): bool => str_contains($message->title, 'approved'));
        Notification::assertSentTo($inventory, SystemMessage::class, fn (SystemMessage $message): bool => str_contains($message->title, 'ready to fulfil'));
    }

    public function test_nobody_approves_a_requisition_they_raised_not_even_a_super_administrator(): void
    {
        $superAdministrator = $this->userWithRole(SystemRole::SuperAdministrator);
        ApprovalWorkflow::query()->delete();

        $requisition = $this->submittedRequisition($superAdministrator, $this->department);

        $this->assertFalse(app(ApprovalService::class)->canAct($superAdministrator, $requisition));
        $this->expectException(BusinessRuleException::class);
        $this->decide($requisition, $superAdministrator);
    }

    public function test_one_person_cannot_decide_two_steps_of_the_same_request(): void
    {
        $this->head->assignRole(SystemRole::FinanceOfficer->value);
        $requisition = $this->submittedRequisition($this->requester, $this->department);

        $requisition = $this->decide($requisition, $this->head);

        $this->assertFalse(app(ApprovalService::class)->canAct($this->head, $requisition));
        $this->assertTrue(app(ApprovalService::class)->canAct($this->finance, $requisition));

        try {
            $this->decide($requisition, $this->head);
            $this->fail('The same person decided two steps.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('already acted', $exception->getMessage());
        }
    }

    public function test_a_step_whose_only_approver_already_acted_is_skipped(): void
    {
        $this->finance->removeRole(SystemRole::FinanceOfficer->value);
        $this->head->assignRole(SystemRole::FinanceOfficer->value);
        $requisition = $this->submittedRequisition($this->requester, $this->department);

        $requisition = $this->decide($requisition, $this->head);

        $this->assertSame(['1.1 Department head: approved', '1.2 Finance: skipped', '1.3 Management: pending'], $this->trail($requisition));
        $this->assertTrue(app(ApprovalService::class)->canAct($this->manager, $requisition));
    }

    public function test_the_department_head_step_is_skipped_when_the_requester_is_the_head(): void
    {
        $requisition = $this->submittedRequisition($this->head, $this->department);

        $this->assertSame(['1.1 Department head: skipped', '1.2 Finance: pending', '1.3 Management: pending'], $this->trail($requisition));
        $this->assertSame('Skipped: the requester is the department head.', $requisition->approvals()->first()->comment);
        $this->assertSame(RequisitionStatus::Submitted, $requisition->status);
    }

    public function test_the_department_head_step_is_skipped_when_the_department_has_no_head(): void
    {
        $department = Department::factory()->create();

        $requisition = $this->submittedRequisition($this->requester, $department);

        $this->assertSame('Skipped: the department has no head.', $requisition->approvals()->first()->comment);
        $this->assertTrue(app(ApprovalService::class)->canAct($this->finance, $requisition));
    }

    public function test_a_step_with_a_minimum_amount_only_applies_at_or_above_it(): void
    {
        ApprovalWorkflow::query()->sole()->steps()->where('sequence', 3)->update(['min_amount' => '1000000']);

        $small = $this->submittedRequisition($this->requester, $this->department);
        $large = $this->submittedRequisition($this->requester, $this->department, [['description' => 'Laptop', 'quantity' => '1', 'estimated_unit_cost' => '1000000']]);

        $this->assertSame(['1.1 Department head: pending', '1.2 Finance: pending'], $this->trail($small));
        $this->assertCount(3, $this->trail($large));

        $small = $this->decide($small, $this->head);
        $this->assertSame(RequisitionStatus::Approved, $this->decide($small, $this->finance)->status);
    }

    public function test_the_active_workflow_with_the_highest_reached_minimum_is_used(): void
    {
        $large = ApprovalWorkflow::factory()->create(['name' => 'Large purchases', 'min_amount' => '5000000']);
        $large->steps()->create(['sequence' => 1, 'name' => 'Managing director', 'approver_type' => ApproverType::Role, 'role_id' => Role::findByName(SystemRole::Manager->value)->id]);
        ApprovalWorkflow::factory()->create(['name' => 'Inactive huge', 'min_amount' => '1000000', 'is_active' => false])
            ->steps()->create(['sequence' => 1, 'approver_type' => ApproverType::User, 'user_id' => $this->finance->id]);

        $requisition = $this->submittedRequisition($this->requester, $this->department, [['description' => 'Vehicle tyres', 'quantity' => '4', 'estimated_unit_cost' => '1500000']]);

        $this->assertSame(['1.1 Managing director: pending'], $this->trail($requisition));
        $this->assertSame($large->id, $requisition->approvals()->sole()->approval_workflow_id);
    }

    public function test_without_any_workflow_a_single_step_goes_to_any_authorised_approver(): void
    {
        ApprovalWorkflow::query()->delete();

        $requisition = $this->submittedRequisition($this->requester, $this->department);

        $step = $requisition->approvals()->sole();
        $this->assertSame(ApproverType::AnyApprover, $step->approver_type);
        $this->assertTrue(app(ApprovalService::class)->canAct($this->head, $requisition));
        $this->assertFalse(app(ApprovalService::class)->canAct($this->requester, $requisition));
        $this->assertSame(RequisitionStatus::Approved, $this->decide($requisition, $this->manager)->status);
    }

    public function test_when_every_configured_step_is_skipped_an_authorised_approver_must_still_decide(): void
    {
        $workflow = ApprovalWorkflow::query()->sole();
        $workflow->steps()->where('sequence', '>', 1)->delete();

        $requisition = $this->submittedRequisition($this->head, $this->department);

        $this->assertSame(['1.1 Department head: skipped', '1.2 Authorised approver: pending'], $this->trail($requisition));
        $this->assertSame(RequisitionStatus::Submitted, $requisition->status);
        $this->assertFalse(app(ApprovalService::class)->canAct($this->head, $requisition));
        $this->assertSame(RequisitionStatus::Approved, $this->decide($requisition, $this->finance)->status);
    }

    public function test_a_named_user_step_can_only_be_decided_by_that_user(): void
    {
        $workflow = ApprovalWorkflow::query()->sole();
        $workflow->steps()->delete();
        $workflow->steps()->create(['sequence' => 1, 'name' => 'Director', 'approver_type' => ApproverType::User, 'user_id' => $this->manager->id]);

        $requisition = $this->submittedRequisition($this->requester, $this->department);

        $this->assertFalse(app(ApprovalService::class)->canAct($this->finance, $requisition));
        $this->assertTrue(app(ApprovalService::class)->canAct($this->manager, $requisition));
    }

    public function test_rejecting_needs_a_reason_and_closes_the_remaining_steps(): void
    {
        Notification::fake();
        $requisition = $this->submittedRequisition($this->requester, $this->department);

        try {
            $this->decide($requisition, $this->head, ApprovalDecision::Reject, '  ');
            $this->fail('Rejected without a reason.');
        } catch (BusinessRuleException) {
            $this->assertSame(ApprovalStatus::Pending, $requisition->approvals()->first()->status);
        }

        $requisition = $this->decide($requisition, $this->head, ApprovalDecision::Reject, 'Not in this year\'s budget');

        $this->assertSame(RequisitionStatus::Rejected, $requisition->status);
        $this->assertNotNull($requisition->rejected_at);
        $this->assertSame(['1.1 Department head: rejected', '1.2 Finance: not_reached', '1.3 Management: not_reached'], $this->trail($requisition));
        Notification::assertSentTo($this->requester, SystemMessage::class, fn (SystemMessage $message): bool => str_contains($message->title, 'rejected'));
        $this->assertFalse(app(ApprovalService::class)->canAct($this->finance, $requisition));
    }

    public function test_a_returned_requisition_is_revised_and_resubmitted_into_a_new_round_with_the_same_number(): void
    {
        $requisition = $this->submittedRequisition($this->requester, $this->department);
        $number = $requisition->number;
        $requisition = $this->decide($requisition, $this->head);
        $requisition = $this->decide($requisition, $this->finance, ApprovalDecision::Return, 'Split the paper by department, please');

        $this->assertSame(RequisitionStatus::Returned, $requisition->status);
        $this->assertTrue($requisition->isEditable());

        app(RequisitionService::class)->updateDraft($requisition, [
            'department_id' => $this->department->id,
            'priority' => 'high',
            'purpose' => 'Revised: paper for finance only',
            'lines' => [['description' => 'Printer paper (ream)', 'quantity' => '4', 'estimated_unit_cost' => '25000']],
        ], null, false);
        $requisition = app(RequisitionService::class)->submit($requisition, $this->requester);

        $this->assertSame($number, $requisition->number);
        $this->assertSame(RequisitionStatus::Submitted, $requisition->status);
        $this->assertSame([
            '1.1 Department head: approved',
            '1.2 Finance: returned',
            '1.3 Management: not_reached',
            '2.1 Department head: pending',
            '2.2 Finance: pending',
            '2.3 Management: pending',
        ], $this->trail($requisition));
        $this->assertTrue(app(ApprovalService::class)->canAct($this->head, $requisition), 'Acting in an earlier round does not block the new round.');
    }

    public function test_steps_are_copied_on_submission_so_later_workflow_edits_do_not_affect_requests_in_progress(): void
    {
        $requisition = $this->submittedRequisition($this->requester, $this->department);

        ApprovalWorkflow::query()->sole()->steps()->where('sequence', '>', 1)->delete();

        $requisition = $this->decide($requisition, $this->head);
        $this->assertSame(RequisitionStatus::InApproval, $requisition->status);
        $this->assertCount(3, $this->trail($requisition));
    }

    public function test_the_requester_can_cancel_before_the_final_decision_which_closes_open_steps(): void
    {
        $requisition = $this->submittedRequisition($this->requester, $this->department);
        $requisition = $this->decide($requisition, $this->head);

        try {
            app(RequisitionService::class)->cancel($requisition, $this->finance, 'Not mine');
            $this->fail('Someone else cancelled the requisition.');
        } catch (BusinessRuleException) {
        }

        $requisition = app(RequisitionService::class)->cancel($requisition, $this->requester, 'No longer needed');

        $this->assertSame(RequisitionStatus::Cancelled, $requisition->status);
        $this->assertSame(['1.1 Department head: approved', '1.2 Finance: not_reached', '1.3 Management: not_reached'], $this->trail($requisition));
        $this->assertFalse(app(ApprovalService::class)->canAct($this->finance, $requisition));
    }

    public function test_an_inactive_approver_is_never_routed_to(): void
    {
        $this->finance->forceFill(['is_active' => false])->save();

        $requisition = $this->submittedRequisition($this->requester, $this->department);
        $requisition = $this->decide($requisition, $this->head);

        $this->assertSame('1.2 Finance: skipped', $this->trail($requisition)[1]);
    }
}
