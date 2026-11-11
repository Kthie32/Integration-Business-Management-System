<?php

namespace Tests\Feature\Sales;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\PaymentService;
use App\Services\Sales\ReceiptService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Receipts are issued with every payment (FR-PAY-04), voided when the payment is reversed (FR-PAY-05),
 * and can be printed or downloaded (FR-PAY-06).
 */
class ReceiptTest extends TestCase
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
            'customer_id' => Customer::factory()->create(['name' => 'Acme Uganda Ltd'])->id,
            'invoice_date' => today()->subDays(3)->toDateString(),
            'lines' => [['description' => 'Website design', 'quantity' => '1', 'unit_price' => '1000000']],
        ], $this->financeOfficer), $this->financeOfficer);
    }

    private function pay(string $amount = '400000', string $reference = 'MM-1'): Payment
    {
        return $this->payments->record($this->invoice, [
            'payment_date' => today()->toDateString(),
            'amount' => $amount,
            'method' => 'mobile_money',
            'reference' => $reference,
            'payer_name' => 'Jane Akello',
        ], $this->financeOfficer);
    }

    public function test_recording_a_payment_issues_a_numbered_receipt(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $this->invoice), [
            'payment_date' => today()->toDateString(),
            'amount' => '250000',
            'method' => 'cash',
        ])->assertRedirect();

        $payment = Payment::query()->sole();
        $receipt = Receipt::query()->sole();

        $this->assertSame('RCT-'.now()->format('Y').'-00001', $receipt->number);
        $this->assertTrue($receipt->payment->is($payment));
        $this->assertSame($this->invoice->id, $receipt->invoice_id);
        $this->assertSame($this->invoice->customer_id, $receipt->customer_id);
        $this->assertSame('250000.00', (string) $receipt->amount);
        $this->assertSame(ReceiptStatus::Valid, $receipt->status);
        $this->assertSame($this->financeOfficer->id, $receipt->created_by);
        $this->assertSame(1, $receipt->statusHistories()->count());
    }

    public function test_a_refused_payment_takes_no_receipt_number(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $this->invoice), [
            'payment_date' => today()->toDateString(),
            'amount' => '2000000',
            'method' => 'cash',
        ])->assertSessionHas('error');

        $this->pay('100000');

        $this->assertSame(0, Payment::query()->where('amount', '2000000')->count());
        $this->assertSame('RCT-'.now()->format('Y').'-00001', Receipt::query()->sole()->number);
    }

    public function test_a_payment_can_never_have_a_second_receipt(): void
    {
        $payment = $this->pay();

        $this->expectException(UniqueConstraintViolationException::class);
        DB::transaction(fn () => app(ReceiptService::class)->issueFor($payment, $this->financeOfficer));
    }

    public function test_reversing_the_payment_voids_its_receipt(): void
    {
        $payment = $this->pay();

        $this->actingAs($this->financeOfficer)
            ->post(route('payments.reverse', $payment), ['reason' => 'Entered against the wrong invoice'])
            ->assertRedirect();

        $receipt = $payment->receipt()->sole();
        $this->assertSame(ReceiptStatus::Void, $receipt->status);
        $this->assertSame('Entered against the wrong invoice', $receipt->void_reason);
        $this->assertSame($this->financeOfficer->id, $receipt->voided_by);
        $this->assertNotNull($receipt->voided_at);
    }

    public function test_reversing_from_the_receipt_reverses_the_payment_and_restores_the_invoice(): void
    {
        $payment = $this->pay('1000000');
        $this->assertSame(InvoiceStatus::Paid, $this->invoice->fresh()->status);
        $receipt = $payment->receipt;

        $this->actingAs($this->financeOfficer)
            ->post(route('receipts.reverse', $receipt), ['reason' => 'Cheque bounced'])
            ->assertRedirect(route('receipts.show', $receipt))
            ->assertSessionHas('success');

        $this->assertSame(ReceiptStatus::Void, $receipt->fresh()->status);
        $this->assertSame(PaymentStatus::Reversed, $payment->fresh()->status);
        $this->assertSame('Cheque bounced', $payment->fresh()->reversal_reason);

        $invoice = $this->invoice->fresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('0.00', (string) $invoice->amount_paid);
        $this->assertSame('1000000.00', (string) $invoice->balance_due);
        $this->assertDatabaseHas('audit_logs', ['event' => 'reversed', 'auditable_id' => $payment->id, 'auditable_type' => $payment->getMorphClass()]);
    }

    public function test_a_reversal_needs_a_reason_and_happens_only_once(): void
    {
        $receipt = $this->pay()->receipt;

        $this->actingAs($this->financeOfficer)->post(route('receipts.reverse', $receipt), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(ReceiptStatus::Valid, $receipt->fresh()->status);

        $this->actingAs($this->financeOfficer)->post(route('receipts.reverse', $receipt), ['reason' => 'Duplicate entry']);
        $this->actingAs($this->financeOfficer)->post(route('receipts.reverse', $receipt), ['reason' => 'Duplicate entry'])
            ->assertForbidden();
    }

    public function test_an_accountant_can_view_but_not_reverse_receipts(): void
    {
        $accountant = $this->userWithRole(SystemRole::Accountant);
        $receipt = $this->pay()->receipt;

        $this->actingAs($accountant)->get(route('receipts.index'))->assertOk()->assertSee($receipt->number);
        $this->actingAs($accountant)->get(route('receipts.show', $receipt))->assertOk()->assertDontSee('Reverse payment');
        $this->actingAs($accountant)->post(route('receipts.reverse', $receipt), ['reason' => 'Not allowed'])->assertForbidden();

        $this->assertSame(ReceiptStatus::Valid, $receipt->fresh()->status);
    }

    public function test_users_without_payment_access_cannot_see_receipts(): void
    {
        $employee = $this->userWithRole(SystemRole::InventoryOfficer);
        $receipt = $this->pay()->receipt;

        $this->actingAs($employee)->get(route('receipts.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('receipts.show', $receipt))->assertForbidden();
        $this->actingAs($employee)->get(route('receipts.pdf', $receipt))->assertForbidden();
        $this->actingAs($employee)->get(route('receipts.print', $receipt))->assertForbidden();
    }

    public function test_the_register_filters_by_status(): void
    {
        $kept = $this->pay('100000', 'MM-1')->receipt;
        $reversed = $this->pay('200000', 'MM-2');
        $this->payments->reverse($reversed, $this->financeOfficer, 'Duplicate entry');

        $this->actingAs($this->financeOfficer)->get(route('receipts.index', ['status' => 'void']))
            ->assertOk()
            ->assertSee($reversed->receipt->number)
            ->assertDontSee($kept->number);
    }

    public function test_the_print_view_and_pdf_show_the_receipt(): void
    {
        $receipt = $this->pay()->receipt;

        $this->actingAs($this->financeOfficer)->get(route('receipts.print', $receipt))
            ->assertOk()
            ->assertSee('RECEIPT')
            ->assertSee($receipt->number)
            ->assertSee('Jane Akello')
            ->assertSee('Acme Uganda Ltd')
            ->assertSee($this->invoice->number)
            ->assertSee('UGX 400,000')
            ->assertSee('MM-1');

        $response = $this->actingAs($this->financeOfficer)->get(route('receipts.pdf', $receipt));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertDatabaseHas('audit_logs', ['event' => 'pdf_downloaded', 'auditable_id' => $receipt->id, 'auditable_type' => $receipt->getMorphClass()]);
    }

    public function test_a_void_receipt_prints_with_a_void_mark(): void
    {
        $payment = $this->pay();
        $this->payments->reverse($payment, $this->financeOfficer, 'Duplicate entry');

        $this->actingAs($this->financeOfficer)->get(route('receipts.print', $payment->receipt))
            ->assertOk()
            ->assertSee('VOID')
            ->assertSee('Duplicate entry');
    }

    public function test_the_invoice_and_payment_pages_link_to_the_receipt(): void
    {
        $receipt = $this->pay()->receipt;

        $this->actingAs($this->financeOfficer)->get(route('invoices.show', $this->invoice))->assertOk()->assertSee($receipt->number);
        $this->actingAs($this->financeOfficer)->get(route('payments.index'))->assertOk()->assertSee($receipt->number);
        $this->actingAs($this->financeOfficer)->get(route('receipts.show', $receipt))->assertOk()->assertSee('Reverse payment');
    }
}
