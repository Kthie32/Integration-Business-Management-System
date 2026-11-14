<?php

namespace Tests\Feature\Integration;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use App\Services\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The procure-and-sell cycle across master data, inventory, quotations, invoicing, payments, statements and reports,
 * driven entirely through the screens by the roles that do each step in practice.
 */
class QuoteToCashTest extends TestCase
{
    use RefreshDatabase;

    private User $inventoryOfficer;

    private User $procurementOfficer;

    private User $financeOfficer;

    private User $manager;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->inventoryOfficer = $this->userWithRole(SystemRole::InventoryOfficer);
        $this->procurementOfficer = $this->userWithRole(SystemRole::ProcurementOfficer);
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->manager = $this->userWithRole(SystemRole::Manager);
        $this->warehouse = Warehouse::factory()->default()->create();
        app(SettingsService::class)->update('finance', ['quotation_approval_threshold' => '1000000']);
    }

    public function test_goods_are_bought_in_quoted_approved_invoiced_paid_and_reported(): void
    {
        $item = $this->inventoryOfficerCreatesAStockItem();
        $supplier = $this->procurementOfficerCreatesASupplier();
        $this->inventoryOfficerReceivesGoods($item, $supplier, '10');
        $customer = $this->financeOfficerCreatesACustomer();

        $quotation = $this->financeOfficerQuotes($customer, $item, '3', '400000');
        $this->assertSame(QuotationStatus::PendingApproval, $quotation->status);
        $this->actingAs($this->financeOfficer)->post(route('quotations.approve', $quotation))->assertForbidden();
        $this->actingAs($this->manager)->post(route('quotations.approve', $quotation), ['comment' => 'Price agreed'])->assertSessionHasNoErrors();
        $this->actingAs($this->financeOfficer)->post(route('quotations.sent', $quotation))->assertSessionHasNoErrors();
        $this->actingAs($this->financeOfficer)->post(route('quotations.accept', $quotation))->assertSessionHasNoErrors();
        $this->assertSame(QuotationStatus::Accepted, $quotation->refresh()->status);

        $this->actingAs($this->financeOfficer)->post(route('quotations.convert', $quotation))->assertSessionHasNoErrors();
        $invoice = Invoice::query()->where('quotation_id', $quotation->id)->sole();
        $this->assertSame('1200000.00', (string) $invoice->total);
        $this->actingAs($this->financeOfficer)->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Issued, $invoice->refresh()->status);
        $this->assertSame('7.000', app(StockService::class)->available($item, $this->warehouse));

        $this->recordPayment($invoice, '500000', 'mobile_money', 'MM-778812');
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);
        $this->recordPayment($invoice, '700000', 'bank_transfer', 'TT-5521');
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('0.00', (string) $invoice->balance_due);
        $this->assertSame(2, $invoice->payments()->has('receipt')->count());

        $statement = $this->actingAs($this->financeOfficer)->get(route('customers.statement', [$customer, 'from' => today()->subDays(30)->toDateString()]));
        $statement->assertOk();
        $statement->assertSee($invoice->number);
        $statement->assertSee($invoice->payments()->first()->receipt->number);

        $revenue = $this->actingAs($this->manager)->get(route('reports.show', ['report' => 'revenue', 'from' => today()->subDays(30)->toDateString(), 'to' => today()->toDateString()]));
        $revenue->assertOk();
        $revenue->assertSee('1,200,000');
        $stock = $this->actingAs($this->inventoryOfficer)->get(route('reports.show', 'stock-issued'));
        $stock->assertOk();
        $stock->assertSee($invoice->number);
        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk();
    }

    public function test_an_invoice_that_needs_more_stock_than_is_on_hand_is_not_issued(): void
    {
        $item = $this->inventoryOfficerCreatesAStockItem();
        $this->inventoryOfficerReceivesGoods($item, $this->procurementOfficerCreatesASupplier(), '2');
        $customer = $this->financeOfficerCreatesACustomer();
        $this->actingAs($this->financeOfficer)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_date' => today()->toDateString(),
            'lines' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => '5', 'unit_price' => '400000']],
        ])->assertSessionHasNoErrors();
        $invoice = Invoice::query()->sole();

        $response = $this->actingAs($this->financeOfficer)->post(route('invoices.issue', $invoice));

        $response->assertSessionHas('error');
        $this->assertSame(InvoiceStatus::Draft, $invoice->refresh()->status);
        $this->assertNull($invoice->number);
        $this->assertSame('2.000', app(StockService::class)->available($item, $this->warehouse));
    }

    private function inventoryOfficerCreatesAStockItem(): Item
    {
        $this->actingAs($this->inventoryOfficer)->post(route('items.store'), [
            'name' => 'Solar inverter 5kVA', 'type' => 'stock', 'unit' => 'pcs',
            'selling_price' => '400000', 'cost_price' => '250000', 'min_stock_level' => '2', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        return Item::query()->where('name', 'Solar inverter 5kVA')->sole();
    }

    private function procurementOfficerCreatesASupplier(): Supplier
    {
        $this->actingAs($this->procurementOfficer)->post(route('suppliers.store'), [
            'name' => 'Kampala Power Supplies', 'payment_terms_days' => '30',
        ])->assertSessionHasNoErrors();

        return Supplier::query()->where('name', 'Kampala Power Supplies')->sole();
    }

    private function inventoryOfficerReceivesGoods(Item $item, Supplier $supplier, string $quantity): void
    {
        $this->actingAs($this->inventoryOfficer)->post(route('goods-receipts.store'), [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => today()->toDateString(),
            'supplier_invoice_ref' => 'KPS-1001',
            'lines' => [['item_id' => $item->id, 'quantity' => $quantity, 'unit_cost' => '250000']],
            'post_now' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(number_format((float) $quantity, 3, '.', ''), app(StockService::class)->available($item, $this->warehouse));
    }

    private function financeOfficerCreatesACustomer(): Customer
    {
        $this->actingAs($this->financeOfficer)->post(route('customers.store'), [
            'type' => 'company', 'name' => 'Lakeside Clinic Ltd', 'payment_terms_days' => '14',
        ])->assertSessionHasNoErrors();

        return Customer::query()->where('name', 'Lakeside Clinic Ltd')->sole();
    }

    private function financeOfficerQuotes(Customer $customer, Item $item, string $quantity, string $price): Quotation
    {
        $this->actingAs($this->financeOfficer)->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'quote_date' => today()->toDateString(),
            'valid_until' => today()->addDays(30)->toDateString(),
            'lines' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $quantity, 'unit_price' => $price]],
            'submit_now' => '1',
        ])->assertSessionHasNoErrors();

        return Quotation::query()->where('customer_id', $customer->id)->sole();
    }

    private function recordPayment(Invoice $invoice, string $amount, string $method, string $reference): void
    {
        $this->actingAs($this->financeOfficer)->post(route('invoices.payments.store', $invoice), [
            'payment_date' => today()->toDateString(), 'amount' => $amount, 'method' => $method, 'reference' => $reference,
        ])->assertSessionHasNoErrors();
    }
}
