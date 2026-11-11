<?php

namespace Tests\Feature\Sales;

use App\Enums\QuotationStatus;
use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\SystemMessage;
use App\Services\Sales\QuotationService;
use App\Services\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuotationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    private User $manager;

    private QuotationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->manager = $this->userWithRole(SystemRole::Manager);
        $this->service = app(QuotationService::class);
    }

    private function draft(string $unitPrice = '100000', ?User $author = null): Quotation
    {
        return $this->service->createDraft([
            'customer_id' => Customer::factory()->create()->id,
            'quote_date' => today()->toDateString(),
            'valid_until' => today()->addDays(30)->toDateString(),
            'lines' => [['description' => 'Consulting', 'quantity' => '1', 'unit_price' => $unitPrice]],
        ], $author ?? $this->financeOfficer);
    }

    private function setThreshold(?string $threshold): void
    {
        app(SettingsService::class)->update('finance', ['quotation_approval_threshold' => $threshold]);
    }

    public function test_submitting_without_a_threshold_numbers_and_approves_the_quotation(): void
    {
        $quotation = $this->draft();

        $this->actingAs($this->financeOfficer)->post(route('quotations.submit', $quotation))->assertRedirect();

        $quotation->refresh();
        $this->assertSame(QuotationStatus::Approved, $quotation->status);
        $this->assertSame('QT-'.now()->format('Y').'-00001', $quotation->number);
        $this->assertNull($quotation->approved_by);
        $this->assertSame(
            [null, QuotationStatus::Draft->value],
            [$quotation->statusHistories->last()->from_status, $quotation->statusHistories->last()->to_status],
        );
    }

    public function test_a_quotation_at_the_threshold_waits_for_approval_and_notifies_approvers(): void
    {
        Notification::fake();
        $this->setThreshold('100000');
        $quotation = $this->draft('100000', $this->manager);
        $otherManager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($this->manager)->post(route('quotations.submit', $quotation))->assertRedirect();

        $this->assertSame(QuotationStatus::PendingApproval, $quotation->fresh()->status);
        Notification::assertSentTo($otherManager, SystemMessage::class);
        Notification::assertNotSentTo($this->manager, SystemMessage::class);
    }

    public function test_a_quotation_below_the_threshold_needs_no_approval(): void
    {
        $this->setThreshold('100000');
        $quotation = $this->draft('99999.99');

        $this->service->submit($quotation, $this->financeOfficer);

        $this->assertSame(QuotationStatus::Approved, $quotation->fresh()->status);
    }

    public function test_the_author_can_never_approve_their_own_quotation(): void
    {
        $this->setThreshold('0');
        $superAdministrator = $this->userWithRole(SystemRole::SuperAdministrator);
        $quotation = $this->service->submit($this->draft('5000', $superAdministrator), $superAdministrator);

        $this->actingAs($superAdministrator)->post(route('quotations.approve', $quotation))->assertForbidden();
        $this->assertSame(QuotationStatus::PendingApproval, $quotation->fresh()->status);

        $this->expectException(BusinessRuleException::class);
        $this->service->approve($quotation, $superAdministrator);
    }

    public function test_another_approver_approves_and_the_author_is_notified(): void
    {
        Notification::fake();
        $this->setThreshold('0');
        $quotation = $this->service->submit($this->draft(), $this->financeOfficer);

        $this->actingAs($this->manager)->post(route('quotations.approve', $quotation), ['comment' => 'Fine'])->assertRedirect();

        $quotation->refresh();
        $this->assertSame(QuotationStatus::Approved, $quotation->status);
        $this->assertSame($this->manager->id, $quotation->approved_by);
        Notification::assertSentTo($this->financeOfficer, SystemMessage::class);
    }

    public function test_a_finance_officer_cannot_approve(): void
    {
        $this->setThreshold('0');
        $quotation = $this->service->submit($this->draft('5000', $this->manager), $this->manager);

        $this->actingAs($this->financeOfficer)->post(route('quotations.approve', $quotation))->assertForbidden();
    }

    public function test_returning_for_changes_needs_a_comment_and_reopens_the_draft_with_its_number(): void
    {
        $this->setThreshold('0');
        $quotation = $this->service->submit($this->draft(), $this->financeOfficer);
        $number = $quotation->number;

        $this->actingAs($this->manager)->post(route('quotations.return', $quotation), ['comment' => ''])->assertSessionHasErrors('comment');

        $this->actingAs($this->manager)->post(route('quotations.return', $quotation), ['comment' => 'Lower the price please'])->assertRedirect();

        $quotation->refresh();
        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertSame($number, $quotation->number);
        $this->assertSame('Lower the price please', $quotation->statusHistories->first()->comment);

        $this->service->submit($quotation, $this->financeOfficer);
        $this->assertSame($number, $quotation->fresh()->number);
    }

    public function test_sent_accepted_flow_and_conversion_hand_over(): void
    {
        $quotation = $this->service->submit($this->draft(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->post(route('quotations.sent', $quotation))->assertRedirect();
        $this->assertSame(QuotationStatus::Sent, $quotation->fresh()->status);

        $this->actingAs($this->financeOfficer)->post(route('quotations.accept', $quotation))->assertRedirect();
        $quotation->refresh();
        $this->assertSame(QuotationStatus::Accepted, $quotation->status);
        $this->assertNotNull($quotation->accepted_at);

        $data = $this->service->invoiceDraftData($quotation);
        $this->assertSame($quotation->id, $data['quotation_id']);
        $this->assertSame('100000.0000', $data['lines'][0]['unit_price']);

        $this->service->markConverted($quotation, $this->financeOfficer, 'INV-TEST');
        $this->assertNotNull($quotation->fresh()->converted_at);

        $this->expectException(BusinessRuleException::class);
        $this->service->markConverted($quotation->fresh(), $this->financeOfficer);
    }

    public function test_rejection_needs_a_reason(): void
    {
        $quotation = $this->service->submit($this->draft(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->post(route('quotations.reject', $quotation), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->financeOfficer)->post(route('quotations.reject', $quotation), ['reason' => 'Too expensive'])->assertRedirect();

        $this->assertSame(QuotationStatus::Rejected, $quotation->fresh()->status);
    }

    public function test_cancelling_needs_a_reason_and_is_final(): void
    {
        $quotation = $this->service->submit($this->draft(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->post(route('quotations.cancel', $quotation), ['reason' => 'Customer withdrew'])->assertRedirect();

        $this->assertSame(QuotationStatus::Cancelled, $quotation->fresh()->status);
        $this->actingAs($this->financeOfficer)->post(route('quotations.sent', $quotation))->assertForbidden();
        $this->actingAs($this->financeOfficer)->post(route('quotations.cancel', $quotation), ['reason' => 'Again please'])->assertForbidden();
    }

    public function test_illegal_transitions_are_refused_by_the_service(): void
    {
        $quotation = $this->draft();

        $this->expectException(BusinessRuleException::class);
        $this->service->markSent($quotation);
    }

    public function test_a_revision_keeps_the_original_until_it_is_submitted(): void
    {
        $original = $this->service->submit($this->draft(), $this->financeOfficer);
        $this->service->markSent($original);

        $this->actingAs($this->financeOfficer)->post(route('quotations.revise', $original))->assertRedirect();

        $revision = Quotation::query()->where('parent_quotation_id', $original->id)->sole();
        $this->assertSame(QuotationStatus::Draft, $revision->status);
        $this->assertSame(1, $revision->revision);
        $this->assertSame(QuotationStatus::Sent, $original->fresh()->status);
        $this->assertSame('100000.00', (string) $revision->total);

        $this->actingAs($this->financeOfficer)->post(route('quotations.revise', $original))->assertForbidden();

        $this->service->submit($revision, $this->financeOfficer);

        $this->assertSame("{$original->number}-R1", $revision->fresh()->number);
        $this->assertSame(QuotationStatus::Superseded, $original->fresh()->status);
    }

    public function test_a_draft_revision_can_be_deleted_leaving_the_original_untouched(): void
    {
        $original = $this->service->submit($this->draft(), $this->financeOfficer);
        $revision = $this->service->revise($original, $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->delete(route('quotations.destroy', $revision))->assertRedirect();

        $this->assertSame(QuotationStatus::Approved, $original->fresh()->status);
        $this->assertTrue($this->financeOfficer->can('revise', $original->fresh()));
    }

    public function test_accepted_quotations_cannot_be_revised(): void
    {
        $quotation = $this->service->markAccepted($this->service->submit($this->draft(), $this->financeOfficer));

        $this->actingAs($this->financeOfficer)->post(route('quotations.revise', $quotation))->assertForbidden();
    }

    public function test_the_expiry_command_expires_only_overdue_open_quotations(): void
    {
        $overdue = $this->service->submit($this->draft(), $this->financeOfficer);
        $overdue->forceFill(['valid_until' => today()->subDay()])->save();
        $current = $this->service->submit($this->draft(), $this->financeOfficer);
        $overdueDraft = $this->draft();
        $overdueDraft->forceFill(['valid_until' => today()->subDay()])->save();

        $this->artisan('ibms:quotations:expire')->expectsOutputToContain('Expired 1 quotation(s).')->assertSuccessful();

        $this->assertSame(QuotationStatus::Expired, $overdue->fresh()->status);
        $this->assertSame(QuotationStatus::Approved, $current->fresh()->status);
        $this->assertSame(QuotationStatus::Draft, $overdueDraft->fresh()->status);
        $this->assertNull($overdue->fresh()->statusHistories->first()->user_id);
    }

    public function test_a_quotation_past_its_validity_cannot_be_accepted(): void
    {
        $quotation = $this->service->submit($this->draft(), $this->financeOfficer);
        $quotation->forceFill(['valid_until' => today()->subDay()])->save();

        $this->actingAs($this->financeOfficer)->from(route('quotations.show', $quotation))
            ->post(route('quotations.accept', $quotation))
            ->assertSessionHas('error');

        $this->assertSame(QuotationStatus::Approved, $quotation->fresh()->status);
    }
}
