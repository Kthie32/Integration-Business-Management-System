<?php

namespace Tests\Feature\Sales;

use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStock;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issuing (numbering + stock out), voiding (stock back) and corrected copies.
 */
class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    private InvoiceService $invoices;

    private StockService $stock;

    private Warehouse $warehouse;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->invoices = app(InvoiceService::class);
        $this->stock = app(StockService::class);
        $this->warehouse = Warehouse::factory()->default()->create();
        $this->item = Item::factory()->create(['name' => 'Toner cartridge']);
        $this->stock->receive($this->item, $this->warehouse, '10', '20000');
    }

    /**
     * @param  list<array<string, mixed>>|null  $lines
     */
    private function draft(?array $lines = null, ?Warehouse $warehouse = null): Invoice
    {
        return $this->invoices->createDraft([
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => ($warehouse ?? $this->warehouse)->id,
            'invoice_date' => today()->toDateString(),
            'lines' => $lines ?? [
                ['item_id' => $this->item->id, 'description' => 'Toner', 'quantity' => '3', 'unit_price' => '30000'],
                ['item_id' => $this->item->id, 'description' => 'Toner (spare)', 'quantity' => '1', 'unit_price' => '30000'],
                ['item_id' => Item::factory()->service()->create()->id, 'description' => 'Fitting', 'quantity' => '1', 'unit_price' => '10000'],
            ],
        ], $this->financeOfficer);
    }

    public function test_issuing_numbers_the_invoice_and_issues_stock_for_stocked_items_only(): void
    {
        $invoice = $this->draft();

        $this->actingAs($this->financeOfficer)->post(route('invoices.issue', $invoice))->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('INV-'.now()->format('Y').'-00001', $invoice->number);
        $this->assertSame($this->financeOfficer->id, $invoice->issued_by);
        $this->assertSame('130000.00', (string) $invoice->balance_due);

        $movement = StockMovement::query()->where('reference_type', $invoice->getMorphClass())->where('reference_id', $invoice->id)->sole();
        $this->assertSame(StockMovementType::SaleIssue, $movement->type);
        $this->assertSame('4.000', (string) $movement->quantity);
        $this->assertSame('6.000', $this->stock->available($this->item, $this->warehouse));
    }

    public function test_issuing_with_insufficient_stock_changes_nothing_and_uses_no_number(): void
    {
        $short = $this->draft([['item_id' => $this->item->id, 'description' => 'Toner', 'quantity' => '11', 'unit_price' => '30000']]);

        try {
            $this->invoices->issue($short, $this->financeOfficer);
            $this->fail('Expected insufficient stock.');
        } catch (InsufficientStock) {
        }

        $this->assertSame(InvoiceStatus::Draft, $short->fresh()->status);
        $this->assertNull($short->fresh()->number);
        $this->assertSame('10.000', $this->stock->available($this->item, $this->warehouse));

        $next = $this->invoices->issue($this->draft(), $this->financeOfficer);
        $this->assertSame('INV-'.now()->format('Y').'-00001', $next->number);
    }

    public function test_stocked_items_need_a_warehouse_and_services_do_not(): void
    {
        $invoice = $this->draft();
        $invoice->forceFill(['warehouse_id' => null])->save();

        $this->expectException(BusinessRuleException::class);
        $this->invoices->issue($invoice, $this->financeOfficer);
    }

    public function test_a_service_only_invoice_issues_without_a_warehouse(): void
    {
        $invoice = $this->draft([['description' => 'Consulting', 'quantity' => '1', 'unit_price' => '50000']]);
        $invoice->forceFill(['warehouse_id' => null])->save();

        $this->assertSame(InvoiceStatus::Issued, $this->invoices->issue($invoice, $this->financeOfficer)->status);
        $this->assertSame(0, StockMovement::query()->where('type', StockMovementType::SaleIssue->value)->count());
    }

    public function test_a_future_dated_invoice_cannot_be_issued(): void
    {
        $invoice = $this->draft();
        $invoice->forceFill(['invoice_date' => today()->addDay(), 'due_date' => today()->addDays(30)])->save();

        $this->expectException(BusinessRuleException::class);
        $this->invoices->issue($invoice, $this->financeOfficer);
    }

    public function test_voiding_returns_the_stock_and_records_the_reason(): void
    {
        $invoice = $this->invoices->issue($this->draft(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->post(route('invoices.void', $invoice), ['reason' => 'Wrong customer'])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Void, $invoice->status);
        $this->assertSame('Wrong customer', $invoice->void_reason);
        $this->assertSame($this->financeOfficer->id, $invoice->voided_by);
        $this->assertSame('0.00', (string) $invoice->balance_due);
        $this->assertSame('10.000', $this->stock->available($this->item, $this->warehouse));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::Reversal->value)->count());
    }

    public function test_voiding_needs_a_reason(): void
    {
        $invoice = $this->invoices->issue($this->draft(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->post(route('invoices.void', $invoice), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
    }

    public function test_an_invoice_with_payments_cannot_be_voided_until_they_are_reversed(): void
    {
        $invoice = $this->invoices->issue($this->draft(), $this->financeOfficer);
        $payment = app(PaymentService::class)->record($invoice, [
            'payment_date' => today()->toDateString(), 'amount' => '1000', 'method' => 'cash',
        ], $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->post(route('invoices.void', $invoice), ['reason' => 'Duplicate invoice'])->assertForbidden();

        try {
            $this->invoices->void($invoice->fresh(), $this->financeOfficer, 'Duplicate invoice');
            $this->fail('Expected the void to be refused.');
        } catch (BusinessRuleException) {
        }

        app(PaymentService::class)->reverse($payment, $this->financeOfficer, 'Entered in error');
        $this->invoices->void($invoice->fresh(), $this->financeOfficer, 'Duplicate invoice');

        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
    }

    public function test_an_accountant_cannot_void(): void
    {
        $invoice = $this->invoices->issue($this->draft(), $this->financeOfficer);

        $this->actingAs($this->userWithRole(SystemRole::Accountant))
            ->post(route('invoices.void', $invoice), ['reason' => 'Wrong customer'])
            ->assertForbidden();
    }

    public function test_a_voided_invoice_can_be_copied_into_a_corrected_draft(): void
    {
        $invoice = $this->invoices->void($this->invoices->issue($this->draft(), $this->financeOfficer), $this->financeOfficer, 'Wrong price');

        $this->actingAs($this->financeOfficer)->post(route('invoices.copy', $invoice))->assertRedirect();

        $copy = Invoice::query()->where('copied_from_invoice_id', $invoice->id)->sole();
        $this->assertSame(InvoiceStatus::Draft, $copy->status);
        $this->assertNull($copy->number);
        $this->assertCount(3, $copy->items);
        $this->assertSame((string) $invoice->total, (string) $copy->total);
    }

    public function test_only_voided_invoices_can_be_copied(): void
    {
        $invoice = $this->invoices->issue($this->draft(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->post(route('invoices.copy', $invoice))->assertForbidden();
    }

    public function test_status_changes_are_recorded_in_the_history(): void
    {
        $invoice = $this->invoices->void($this->invoices->issue($this->draft(), $this->financeOfficer), $this->financeOfficer, 'Cancelled order');

        $this->assertSame(
            [InvoiceStatus::Void->value, InvoiceStatus::Issued->value, InvoiceStatus::Draft->value],
            $invoice->statusHistories()->pluck('to_status')->all(),
        );
    }
}
