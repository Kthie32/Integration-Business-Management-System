<?php

namespace Tests\Feature\Sales;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recording and reversing payments, and the invoice balance and status that follow from them.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    private PaymentService $payments;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->payments = app(PaymentService::class);
        $invoices = app(InvoiceService::class);
        $this->invoice = $invoices->issue($invoices->createDraft([
            'customer_id' => Customer::factory()->create()->id,
            'invoice_date' => today()->subDays(3)->toDateString(),
            'lines' => [['description' => 'Website design', 'quantity' => '1', 'unit_price' => '1000000']],
        ], $this->financeOfficer), $this->financeOfficer);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'payment_date' => today()->toDateString(),
            'amount' => '400000',
            'method' => 'mobile_money',
            'reference' => 'MM-12345',
            'payer_name' => 'Jane Akello',
            ...$overrides,
        ];
    }

    public function test_a_partial_payment_reduces_the_balance(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $this->invoice), $this->payload())
            ->assertRedirect(route('invoices.show', $this->invoice));

        $payment = Payment::query()->sole();
        $this->assertSame('PMT-'.now()->format('Y').'-00001', $payment->number);
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame($this->invoice->customer_id, $payment->customer_id);

        $this->invoice->refresh();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $this->invoice->status);
        $this->assertSame('400000.00', (string) $this->invoice->amount_paid);
        $this->assertSame('600000.00', (string) $this->invoice->balance_due);
    }

    public function test_paying_the_balance_marks_the_invoice_paid(): void
    {
        $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);
        $this->payments->record($this->invoice, $this->payload(['amount' => '600000', 'method' => 'cash', 'reference' => null]), $this->financeOfficer);

        $this->invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $this->invoice->status);
        $this->assertSame('0.00', (string) $this->invoice->balance_due);
    }

    public function test_overpayment_is_rejected(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $this->invoice), $this->payload(['amount' => '1000000.01']))
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(InvoiceStatus::Issued, $this->invoice->fresh()->status);
    }

    public function test_a_paid_invoice_accepts_no_further_payments(): void
    {
        $this->payments->record($this->invoice, $this->payload(['amount' => '1000000']), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $this->invoice), $this->payload(['reference' => 'MM-2']))
            ->assertForbidden();
    }

    public function test_draft_and_void_invoices_accept_no_payments(): void
    {
        $invoices = app(InvoiceService::class);
        $draft = $invoices->createDraft([
            'customer_id' => $this->invoice->customer_id,
            'invoice_date' => today()->toDateString(),
            'lines' => [['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '1000']],
        ], $this->financeOfficer);
        $void = $invoices->void($this->invoice, $this->financeOfficer, 'Raised in error');

        foreach ([$draft, $void] as $invoice) {
            try {
                $this->payments->record($invoice, $this->payload(), $this->financeOfficer);
                $this->fail('Expected the payment to be refused.');
            } catch (BusinessRuleException) {
            }
        }

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_non_cash_payments_need_a_reference_and_cash_does_not(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $this->invoice), $this->payload(['reference' => '']))
            ->assertSessionHasErrors('reference');

        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $this->invoice), $this->payload(['method' => 'cash', 'reference' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_payment_dates_cannot_be_in_the_future_or_before_the_invoice(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $this->invoice), $this->payload(['payment_date' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors('payment_date');

        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $this->invoice), $this->payload(['payment_date' => today()->subDays(4)->toDateString()]))
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_reference_cannot_be_used_twice_for_the_same_method(): void
    {
        $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);

        $this->expectException(BusinessRuleException::class);
        $this->payments->record($this->invoice, $this->payload(['amount' => '1000']), $this->financeOfficer);
    }

    public function test_the_same_reference_may_be_used_with_another_method(): void
    {
        $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);
        $this->payments->record($this->invoice, $this->payload(['method' => 'bank_transfer', 'amount' => '1000']), $this->financeOfficer);

        $this->assertSame(2, Payment::query()->count());
    }

    public function test_reversing_a_payment_restores_the_balance_and_frees_its_reference(): void
    {
        $first = $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);
        $this->payments->record($this->invoice, $this->payload(['amount' => '600000', 'reference' => 'MM-2']), $this->financeOfficer);
        $this->assertSame(InvoiceStatus::Paid, $this->invoice->fresh()->status);

        $this->actingAs($this->financeOfficer)->post(route('payments.reverse', $first), ['reason' => 'Bounced transaction'])->assertRedirect();

        $first->refresh();
        $this->assertSame(PaymentStatus::Reversed, $first->status);
        $this->assertSame('Bounced transaction', $first->reversal_reason);
        $this->assertSame($this->financeOfficer->id, $first->reversed_by);

        $this->invoice->refresh();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $this->invoice->status);
        $this->assertSame('600000.00', (string) $this->invoice->amount_paid);
        $this->assertSame('400000.00', (string) $this->invoice->balance_due);

        $again = $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);
        $this->assertSame('MM-12345', $again->reference);
        $this->assertSame(InvoiceStatus::Paid, $this->invoice->fresh()->status);
    }

    public function test_reversing_every_payment_returns_the_invoice_to_issued(): void
    {
        $payment = $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);

        $this->payments->reverse($payment, $this->financeOfficer, 'Wrong invoice');

        $this->assertSame(InvoiceStatus::Issued, $this->invoice->fresh()->status);
        $this->assertSame('1000000.00', (string) $this->invoice->fresh()->balance_due);
    }

    public function test_a_payment_cannot_be_reversed_twice(): void
    {
        $payment = $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);
        $this->payments->reverse($payment, $this->financeOfficer, 'Wrong invoice');

        $this->actingAs($this->financeOfficer)->post(route('payments.reverse', $payment), ['reason' => 'Again please'])->assertForbidden();

        $this->expectException(BusinessRuleException::class);
        $this->payments->reverse($payment, $this->financeOfficer, 'Again please');
    }

    public function test_reversal_needs_a_reason_and_the_reverse_permission(): void
    {
        $payment = $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->post(route('payments.reverse', $payment), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->userWithRole(SystemRole::Accountant))->post(route('payments.reverse', $payment), ['reason' => 'Wrong invoice'])->assertForbidden();

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
    }

    public function test_reversals_are_audited(): void
    {
        $payment = $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer);
        $this->payments->reverse($payment, $this->financeOfficer, 'Wrong invoice');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'reversed',
            'auditable_type' => $payment->getMorphClass(),
            'auditable_id' => $payment->id,
            'user_id' => $this->financeOfficer->id,
        ]);
    }

    public function test_the_payments_register_lists_and_totals_completed_payments(): void
    {
        $this->payments->record($this->invoice, $this->payload(), $this->financeOfficer);
        $reversed = $this->payments->record($this->invoice, $this->payload(['amount' => '100000', 'reference' => 'MM-9']), $this->financeOfficer);
        $this->payments->reverse($reversed, $this->financeOfficer, 'Duplicate entry');

        $this->actingAs($this->financeOfficer)->get(route('payments.index'))
            ->assertOk()
            ->assertSee('MM-12345')
            ->assertSee('MM-9')
            ->assertSee('UGX 400,000');
    }
}
