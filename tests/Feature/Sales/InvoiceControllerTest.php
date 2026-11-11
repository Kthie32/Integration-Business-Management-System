<?php

namespace Tests\Feature\Sales;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    private Customer $customer;

    private TaxRate $vat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->customer = Customer::factory()->create(['payment_terms_days' => 14]);
        $this->vat = TaxRate::factory()->default()->create(['rate' => '18.0000']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'customer_id' => $this->customer->id,
            'invoice_date' => today()->toDateString(),
            'lines' => [['description' => 'Installation', 'quantity' => '2', 'unit_price' => '50000', 'tax_rate_id' => $this->vat->id]],
            ...$overrides,
        ];
    }

    public function test_a_draft_is_saved_with_server_calculated_totals_and_the_customers_terms(): void
    {
        $payload = $this->payload();
        $payload['lines'][] = ['description' => '', 'quantity' => '', 'unit_price' => ''];

        $this->actingAs($this->financeOfficer)->post(route('invoices.store'), $payload)->assertRedirect();

        $invoice = Invoice::query()->sole();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->number);
        $this->assertCount(1, $invoice->items);
        $this->assertSame('100000.00', (string) $invoice->subtotal);
        $this->assertSame('18000.00', (string) $invoice->tax_total);
        $this->assertSame('118000.00', (string) $invoice->total);
        $this->assertSame('118000.00', (string) $invoice->balance_due);
        $this->assertSame(today()->addDays(14)->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame($this->financeOfficer->id, $invoice->created_by);
    }

    public function test_save_and_issue_numbers_the_invoice(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('invoices.store'), $this->payload(['issue_now' => '1']))->assertRedirect();

        $invoice = Invoice::query()->sole();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('INV-'.now()->format('Y').'-00001', $invoice->number);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->financeOfficer)->from(route('invoices.create'))->post(route('invoices.store'), $this->payload([
            'customer_id' => Customer::factory()->inactive()->create()->id,
            'due_date' => today()->subDay()->toDateString(),
            'lines' => [['description' => 'X', 'quantity' => '0', 'unit_price' => '-1']],
        ]))->assertSessionHasErrors(['customer_id', 'due_date', 'lines.0.quantity', 'lines.0.unit_price']);

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_issued_invoices_cannot_be_edited_or_deleted(): void
    {
        $service = app(InvoiceService::class);
        $invoice = $service->issue($service->createDraft($this->payload(), $this->financeOfficer), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($this->financeOfficer)->put(route('invoices.update', $invoice), $this->payload())->assertForbidden();
        $this->actingAs($this->financeOfficer)->delete(route('invoices.destroy', $invoice))->assertForbidden();
        $this->assertSame('118000.00', (string) $invoice->fresh()->total);
    }

    public function test_a_draft_can_be_updated_and_deleted(): void
    {
        $invoice = app(InvoiceService::class)->createDraft($this->payload(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->put(route('invoices.update', $invoice), $this->payload([
            'lines' => [['description' => 'Support', 'quantity' => '1', 'unit_price' => '20000']],
        ]))->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame('20000.00', (string) $invoice->fresh()->total);

        $this->actingAs($this->financeOfficer)->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));
        $this->assertSoftDeleted($invoice);
    }

    public function test_an_accepted_quotation_converts_once_into_a_draft_with_the_quoted_tax_rate(): void
    {
        Warehouse::factory()->default()->create();
        $quotations = app(QuotationService::class);
        $quotation = $quotations->markAccepted($quotations->submit($quotations->createDraft([
            'customer_id' => $this->customer->id,
            'quote_date' => today()->toDateString(),
            'valid_until' => today()->addDays(30)->toDateString(),
            'lines' => [['description' => 'Router', 'quantity' => '1', 'unit_price' => '100000', 'tax_rate_id' => $this->vat->id]],
        ], $this->financeOfficer), $this->financeOfficer));
        $this->vat->update(['rate' => '20.0000']);

        $this->actingAs($this->financeOfficer)->post(route('quotations.convert', $quotation))->assertRedirect();

        $invoice = Invoice::query()->sole();
        $this->assertSame($quotation->id, $invoice->quotation_id);
        $this->assertSame('118000.00', (string) $invoice->total);
        $this->assertSame(Warehouse::default()->id, $invoice->warehouse_id);
        $this->assertTrue($quotation->fresh()->isConverted());

        $this->actingAs($this->financeOfficer)->post(route('quotations.convert', $quotation))->assertForbidden();
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_deleting_a_converted_draft_lets_the_quotation_be_invoiced_again(): void
    {
        $quotation = Quotation::factory()->for($this->customer)->status(QuotationStatus::Accepted)->create();
        $quotation->items()->create([
            'description' => 'Consulting', 'quantity' => '1', 'unit_price' => '1000', 'gross_amount' => '1000',
            'taxable_amount' => '1000', 'tax_amount' => '0', 'line_total' => '1000',
        ]);
        $service = app(InvoiceService::class);
        $draft = $service->createFromQuotation($quotation, $this->financeOfficer);

        $service->deleteDraft($draft);

        $this->assertFalse($quotation->fresh()->isConverted());
        $this->assertNotNull($service->createFromQuotation($quotation->fresh(), $this->financeOfficer)->id);
    }

    public function test_the_index_filters_overdue_invoices(): void
    {
        $service = app(InvoiceService::class);
        $overdue = $service->issue($service->createDraft($this->payload([
            'invoice_date' => today()->subDays(20)->toDateString(),
            'due_date' => today()->subDays(5)->toDateString(),
        ]), $this->financeOfficer), $this->financeOfficer);
        $current = $service->issue($service->createDraft($this->payload(), $this->financeOfficer), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->get(route('invoices.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertSee($overdue->number)
            ->assertDontSee($current->number);
    }

    public function test_the_show_page_renders_lines_payments_and_actions(): void
    {
        $item = Item::factory()->create(['name' => 'Patch cable']);
        $invoice = app(InvoiceService::class)->createDraft($this->payload([
            'lines' => [['item_id' => $item->id, 'description' => 'Patch cable', 'quantity' => '3', 'unit_price' => '5000']],
        ]), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Patch cable')
            ->assertSee('Issue');
    }
}
