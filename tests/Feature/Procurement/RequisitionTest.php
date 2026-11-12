<?php

namespace Tests\Feature\Procurement;

use App\Enums\RequisitionStatus;
use App\Enums\SystemRole;
use App\Models\Department;
use App\Models\Item;
use App\Models\Requisition;
use App\Models\User;
use Database\Seeders\ApprovalWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesRequisitions;
use Tests\TestCase;

class RequisitionTest extends TestCase
{
    use CreatesRequisitions, RefreshDatabase;

    private Department $department;

    private User $head;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ApprovalWorkflowSeeder::class);

        [$this->department, $this->head] = $this->departmentWithHead();
        $this->requester = $this->userWithRole(SystemRole::GeneralEmployee);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'department_id' => $this->department->id,
            'required_by_date' => today()->addWeek()->toDateString(),
            'priority' => 'high',
            'purpose' => 'Stationery for the new interns',
            'lines' => [
                ['item_id' => '', 'description' => 'Notebooks A5', 'quantity' => '20', 'estimated_unit_cost' => '3500'],
            ],
            ...$overrides,
        ];
    }

    public function test_a_draft_is_saved_with_catalogue_and_free_text_lines_and_estimated_totals(): void
    {
        $item = Item::factory()->create(['name' => 'Toner cartridge', 'unit' => 'pcs', 'cost_price' => '180000']);

        $this->actingAs($this->requester)->post(route('requisitions.store'), $this->payload([
            'lines' => [
                ['item_id' => (string) $item->id, 'description' => '', 'quantity' => '2', 'estimated_unit_cost' => ''],
                ['item_id' => '', 'description' => 'Notebooks A5', 'quantity' => '20', 'estimated_unit_cost' => '3500'],
                ['item_id' => '', 'description' => '', 'quantity' => '', 'estimated_unit_cost' => ''],
            ],
        ]))->assertSessionHasNoErrors();

        $requisition = Requisition::query()->with('items')->sole();
        $this->assertSame(RequisitionStatus::Draft, $requisition->status);
        $this->assertNull($requisition->number);
        $this->assertSame($this->requester->id, $requisition->requested_by);
        $this->assertSame('430000.00', (string) $requisition->estimated_total);
        $this->assertSame(['Toner cartridge', 'Notebooks A5'], $requisition->items->pluck('description')->all());
        $this->assertSame(['180000.00', '3500.00'], $requisition->items->map(fn ($line): string => (string) $line->estimated_unit_cost)->all());
        $this->assertSame(['360000.00', '70000.00'], $requisition->items->map(fn ($line): string => (string) $line->estimated_total)->all());
        $this->assertSame('pcs', $requisition->items[0]->unit);
    }

    public function test_lines_must_be_described_and_have_a_positive_quantity(): void
    {
        $this->actingAs($this->requester)->post(route('requisitions.store'), $this->payload([
            'lines' => [['item_id' => '', 'description' => '', 'quantity' => '0', 'estimated_unit_cost' => '10']],
        ]))->assertSessionHasErrors(['lines.0.description', 'lines.0.quantity']);

        $this->actingAs($this->requester)->post(route('requisitions.store'), $this->payload(['lines' => []]))
            ->assertSessionHasErrors('lines');

        $this->actingAs($this->requester)->post(route('requisitions.store'), $this->payload(['required_by_date' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('required_by_date');

        $this->assertSame(0, Requisition::query()->count());
    }

    public function test_save_and_submit_numbers_the_requisition_and_locks_it(): void
    {
        $this->actingAs($this->requester)->post(route('requisitions.store'), [...$this->payload(), 'action' => 'submit'])
            ->assertSessionHasNoErrors();

        $requisition = Requisition::query()->sole();
        $this->assertSame(RequisitionStatus::Submitted, $requisition->status);
        $this->assertSame('REQ-'.now()->format('Y').'-00001', $requisition->number);

        $this->actingAs($this->requester)->get(route('requisitions.edit', $requisition))->assertForbidden();
        $this->actingAs($this->requester)->put(route('requisitions.update', $requisition), $this->payload())->assertForbidden();
        $this->actingAs($this->requester)->delete(route('requisitions.destroy', $requisition))->assertForbidden();
    }

    public function test_only_the_requester_edits_and_deletes_a_draft(): void
    {
        $requisition = $this->draftRequisition($this->requester, $this->department);
        $other = $this->userWithRole(SystemRole::GeneralEmployee);

        $this->actingAs($other)->put(route('requisitions.update', $requisition), $this->payload())->assertForbidden();

        $this->actingAs($this->requester)->put(route('requisitions.update', $requisition), $this->payload(['purpose' => 'Updated purpose']))
            ->assertRedirect(route('requisitions.show', $requisition));
        $this->assertSame('Updated purpose', $requisition->fresh()->purpose);

        $this->actingAs($this->requester)->delete(route('requisitions.destroy', $requisition))->assertRedirect(route('requisitions.index'));
        $this->assertSoftDeleted($requisition);
    }

    public function test_visibility_follows_ownership_department_headship_and_view_all(): void
    {
        $requisition = $this->submittedRequisition($this->requester, $this->department);
        $stranger = $this->userWithRole(SystemRole::GeneralEmployee);
        [, $otherHead] = $this->departmentWithHead();
        $accountant = $this->userWithRole(SystemRole::Accountant);

        $this->actingAs($this->requester)->get(route('requisitions.show', $requisition))->assertOk()->assertSee($requisition->number);
        $this->actingAs($this->head)->get(route('requisitions.show', $requisition))->assertOk()->assertSee('Your decision');
        $this->actingAs($accountant)->get(route('requisitions.show', $requisition))->assertOk()->assertDontSee('Your decision');
        $this->actingAs($stranger)->get(route('requisitions.show', $requisition))->assertForbidden();
        $this->actingAs($otherHead)->get(route('requisitions.show', $requisition))->assertForbidden();

        $this->actingAs($stranger)->get(route('requisitions.index'))->assertOk()->assertDontSee($requisition->number);
        $this->actingAs($this->head)->get(route('requisitions.index'))->assertOk()->assertSee($requisition->number);
        $this->actingAs($accountant)->get(route('requisitions.index', ['status' => 'pending']))->assertOk()->assertSee($requisition->number);
    }

    public function test_approvers_decide_through_the_web_and_rejecting_needs_a_reason(): void
    {
        $requisition = $this->submittedRequisition($this->requester, $this->department);
        $finance = $this->userWithRole(SystemRole::FinanceOfficer);

        $this->actingAs($finance)->post(route('requisitions.approve', $requisition))->assertForbidden();
        $this->actingAs($this->requester)->post(route('requisitions.approve', $requisition))->assertForbidden();

        $this->actingAs($this->head)->post(route('requisitions.approve', $requisition), ['comment' => 'Fine'])
            ->assertRedirect(route('requisitions.show', $requisition));
        $this->assertSame(RequisitionStatus::InApproval, $requisition->fresh()->status);

        $this->actingAs($finance)->post(route('requisitions.reject', $requisition), ['comment' => ''])->assertSessionHasErrors('comment');
        $this->actingAs($finance)->post(route('requisitions.reject', $requisition), ['comment' => 'Buy from the framework supplier'])
            ->assertRedirect(route('requisitions.show', $requisition));

        $this->assertSame(RequisitionStatus::Rejected, $requisition->fresh()->status);
        $this->actingAs($finance)->get(route('requisitions.show', $requisition))->assertOk()->assertSee('Buy from the framework supplier');
    }

    public function test_return_and_resubmit_through_the_web(): void
    {
        $requisition = $this->submittedRequisition($this->requester, $this->department);

        $this->actingAs($this->head)->post(route('requisitions.return', $requisition), ['comment' => 'Add the quantities per intern']);
        $this->assertSame(RequisitionStatus::Returned, $requisition->fresh()->status);

        $this->actingAs($this->requester)->get(route('requisitions.edit', $requisition))->assertOk()->assertSee('Returned for revision');
        $this->actingAs($this->requester)->put(route('requisitions.update', $requisition), [...$this->payload(), 'action' => 'submit'])
            ->assertRedirect(route('requisitions.show', $requisition));

        $this->assertSame(RequisitionStatus::Submitted, $requisition->fresh()->status);
        $this->assertSame(2, $requisition->fresh()->currentApprovalRound());
    }

    public function test_the_requester_cancels_with_a_reason(): void
    {
        $requisition = $this->submittedRequisition($this->requester, $this->department);

        $this->actingAs($this->head)->post(route('requisitions.cancel', $requisition), ['reason' => 'Not needed'])->assertForbidden();
        $this->actingAs($this->requester)->post(route('requisitions.cancel', $requisition), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->requester)->post(route('requisitions.cancel', $requisition), ['reason' => 'Bought by head office'])
            ->assertRedirect(route('requisitions.show', $requisition));

        $this->assertSame(RequisitionStatus::Cancelled, $requisition->fresh()->status);
    }

    public function test_an_attachment_is_stored_privately_and_only_viewers_can_download_it(): void
    {
        Storage::fake(Requisition::DISK);

        $this->actingAs($this->requester)->post(route('requisitions.store'), [
            ...$this->payload(),
            'attachment' => UploadedFile::fake()->create('pro-forma.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $requisition = Requisition::query()->sole();
        $this->assertSame('pro-forma.pdf', $requisition->attachment_name);
        Storage::disk(Requisition::DISK)->assertExists($requisition->attachment_path);

        $this->actingAs($this->requester)->get(route('requisitions.attachment', $requisition))->assertOk()->assertDownload('pro-forma.pdf');
        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get(route('requisitions.attachment', $requisition))->assertForbidden();

        $this->actingAs($this->requester)->post(route('requisitions.store'), [
            ...$this->payload(),
            'attachment' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('attachment');
    }

    public function test_pdf_and_print_show_the_approval_trail_and_signature_block(): void
    {
        $this->userWithRole(SystemRole::FinanceOfficer);
        $requisition = $this->submittedRequisition($this->requester, $this->department);
        $requisition = $this->decide($requisition, $this->head, comment: 'Supported');

        $this->actingAs($this->requester)->get(route('requisitions.print', $requisition))
            ->assertOk()
            ->assertSee('REQUISITION')
            ->assertSee('Approval trail')
            ->assertSee('Supported')
            ->assertSee('Requested by')
            ->assertSee('NOT APPROVED');

        $this->actingAs($this->requester)->get(route('requisitions.pdf', $requisition))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get(route('requisitions.pdf', $requisition))->assertForbidden();
    }

    public function test_dashboard_shows_requisitions_in_approval_and_items_awaiting_the_user(): void
    {
        $this->submittedRequisition($this->requester, $this->department);

        $this->actingAs($this->head)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Awaiting my approval')
            ->assertSee('Requisitions in approval');
    }

    public function test_pages_render(): void
    {
        $requisition = $this->draftRequisition($this->requester, $this->department);

        $this->actingAs($this->requester)->get(route('requisitions.index'))->assertOk()->assertSee($requisition->displayNumber());
        $this->actingAs($this->requester)->get(route('requisitions.create'))->assertOk();
        $this->actingAs($this->requester)->get(route('requisitions.edit', $requisition))->assertOk();
        $this->actingAs($this->requester)->get(route('requisitions.show', $requisition))->assertOk()->assertSee('Submit for approval');
    }
}
