<?php

namespace Tests\Feature\Sales;

use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Sales\CustomerStatementService;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer statement of account (FR-PAY-06): opening balance, every balance-changing event in the
 * period with a running balance, and a closing balance that matches what the customer owes.
 */
class CustomerStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    private Customer $customer;

    private Invoice $older;

    private Invoice $recent;

    private Invoice $voided;

    private Payment $reversedPayment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->customer = Customer::factory()->create(['name' => 'Acme Uganda Ltd']);
        $payments = app(PaymentService::class);

        $this->older = $this->issueInvoice(60, '500000');
        $payments->record($this->older, $this->payment(50, '200000', 'BANK-1'), $this->financeOfficer);

        $this->recent = $this->issueInvoice(10, '300000');
        $this->reversedPayment = $payments->record($this->recent, $this->payment(5, '100000', 'BANK-2'), $this->financeOfficer);
        $payments->reverse($this->reversedPayment, $this->financeOfficer, 'Cheque bounced');

        $this->voided = app(InvoiceService::class)->void($this->issueInvoice(8, '50000'), $this->financeOfficer, 'Raised in error');

        // Another customer's documents never appear.
        $this->issueInvoice(5, '999000', Customer::factory()->create());
    }

    private function issueInvoice(int $daysAgo, string $price, ?Customer $customer = null): Invoice
    {
        $invoices = app(InvoiceService::class);

        return $invoices->issue($invoices->createDraft([
            'customer_id' => ($customer ?? $this->customer)->id,
            'invoice_date' => today()->subDays($daysAgo)->toDateString(),
            'lines' => [['description' => 'Services', 'quantity' => '1', 'unit_price' => $price]],
        ], $this->financeOfficer), $this->financeOfficer);
    }

    /**
     * @return array<string, string>
     */
    private function payment(int $daysAgo, string $amount, string $reference): array
    {
        return ['payment_date' => today()->subDays($daysAgo)->toDateString(), 'amount' => $amount, 'method' => 'bank_transfer', 'reference' => $reference];
    }

    public function test_the_statement_has_an_opening_balance_running_balance_and_closing_balance(): void
    {
        $statement = app(CustomerStatementService::class)->build($this->customer, today()->subDays(30), today());

        $this->assertSame('300000.00', $statement['opening']);
        $this->assertSame(
            [
                ['Invoice', '300000.00', null, '600000.00'],
                ['Invoice', '50000.00', null, '650000.00'],
                ['Payment', null, '100000.00', '550000.00'],
                ['Payment reversed', '100000.00', null, '650000.00'],
                ['Invoice voided', null, '50000.00', '600000.00'],
            ],
            $statement['lines']->map(fn (array $line): array => [$line['type'], $line['debit'], $line['credit'], $line['balance']])->all(),
        );
        $this->assertSame('450000.00', $statement['total_debit']);
        $this->assertSame('150000.00', $statement['total_credit']);
        $this->assertSame('600000.00', $statement['closing']);
        $this->assertSame($this->customer->outstandingBalance(), $statement['closing']);
    }

    public function test_events_after_the_end_date_are_left_out(): void
    {
        $statement = app(CustomerStatementService::class)->build($this->customer, today()->subDays(30), today()->subDay());

        $this->assertCount(3, $statement['lines']);
        $this->assertSame('550000.00', $statement['closing']);
    }

    public function test_a_period_with_no_activity_carries_the_opening_balance(): void
    {
        $statement = app(CustomerStatementService::class)->build($this->customer, today()->subDays(45), today()->subDays(40));

        $this->assertTrue($statement['lines']->isEmpty());
        $this->assertSame('300000.00', $statement['opening']);
        $this->assertSame('300000.00', $statement['closing']);
    }

    public function test_the_statement_page_shows_the_lines_and_defaults_to_the_current_year(): void
    {
        $this->actingAs($this->financeOfficer)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee(route('customers.statement', $this->customer));

        $this->actingAs($this->financeOfficer)
            ->get(route('customers.statement', [$this->customer, 'from' => today()->subDays(90)->toDateString()]))
            ->assertOk()
            ->assertSee('Statement of account')
            ->assertSee($this->older->number)
            ->assertSee($this->voided->number)
            ->assertSee($this->reversedPayment->receipt->number)
            ->assertSee('Cheque bounced')
            ->assertSee('UGX 600,000');

        $this->actingAs($this->financeOfficer)->get(route('customers.statement', $this->customer))
            ->assertOk()
            ->assertViewHas('statement', fn (array $statement): bool => $statement['from']->isSameDay(today()->startOfYear()) && $statement['to']->isSameDay(today()));
    }

    public function test_an_invalid_range_is_rejected(): void
    {
        $this->actingAs($this->financeOfficer)
            ->get(route('customers.statement', [$this->customer, 'from' => today()->toDateString(), 'to' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('to');
    }

    public function test_the_statement_prints_and_downloads_as_pdf(): void
    {
        $range = ['from' => today()->subDays(90)->toDateString(), 'to' => today()->toDateString()];

        $this->actingAs($this->financeOfficer)->get(route('customers.statement.print', [$this->customer, ...$range]))
            ->assertOk()
            ->assertSee('STATEMENT OF ACCOUNT')
            ->assertSee('Acme Uganda Ltd')
            ->assertSee('Amount due')
            ->assertSee('UGX 600,000');

        $response = $this->actingAs($this->financeOfficer)->get(route('customers.statement.pdf', [$this->customer, ...$range]));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString($this->customer->code, (string) $response->headers->get('content-disposition'));
    }

    public function test_the_statement_needs_customer_invoice_and_payment_access(): void
    {
        $inventoryOfficer = $this->userWithRole(SystemRole::InventoryOfficer);
        $this->actingAs($inventoryOfficer)->get(route('customers.statement', $this->customer))->assertForbidden();
        $this->actingAs($inventoryOfficer)->get(route('customers.statement.pdf', $this->customer))->assertForbidden();

        $accountant = $this->userWithRole(SystemRole::Accountant);
        $this->actingAs($accountant)->get(route('customers.statement', $this->customer))->assertOk();
    }
}
