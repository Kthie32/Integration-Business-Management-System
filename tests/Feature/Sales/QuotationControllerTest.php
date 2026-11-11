<?php

namespace Tests\Feature\Sales;

use App\Enums\QuotationStatus;
use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationControllerTest extends TestCase
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
        $this->customer = Customer::factory()->create();
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
            'quote_date' => today()->toDateString(),
            'valid_until' => today()->addDays(30)->toDateString(),
            'lines' => [
                ['description' => 'Installation service', 'quantity' => '2', 'unit_price' => '50000', 'tax_rate_id' => $this->vat->id],
            ],
            ...$overrides,
        ];
    }

    public function test_saving_a_draft_calculates_totals_on_the_server(): void
    {
        $item = Item::factory()->create(['name' => 'Office chair']);

        $this->actingAs($this->financeOfficer)->post(route('quotations.store'), $this->payload([
            'document_discount_type' => 'amount',
            'document_discount_value' => '20000',
            'total' => '1',
            'lines' => [
                ['item_id' => $item->id, 'description' => '', 'quantity' => '1', 'unit_price' => '100000', 'discount_amount' => '10000', 'tax_rate_id' => $this->vat->id],
                ['description' => 'Delivery', 'quantity' => '1', 'unit_price' => '30000'],
                ['item_id' => '', 'description' => '', 'quantity' => ''],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $quotation = Quotation::query()->sole();

        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertNull($quotation->number);
        $this->assertSame($this->financeOfficer->id, $quotation->created_by);
        $this->assertSame('130000.00', (string) $quotation->subtotal);
        $this->assertSame('30000.00', (string) $quotation->discount_total);
        $this->assertSame('13500.00', (string) $quotation->tax_total);
        $this->assertSame('113500.00', (string) $quotation->total);
        $this->assertSame(['Office chair', 'Delivery'], $quotation->items->pluck('description')->all());
        $this->assertSame('18.0000', (string) $quotation->items[0]->tax_rate);
        $this->assertSame('0.0000', (string) $quotation->items[1]->tax_rate);
    }

    public function test_changing_a_tax_rate_later_does_not_change_saved_quotations(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('quotations.store'), $this->payload())->assertSessionHasNoErrors();

        $this->vat->update(['rate' => '20.0000']);

        $this->assertSame('118000.00', (string) Quotation::query()->sole()->fresh()->total);
    }

    public function test_invalid_input_is_rejected_with_field_errors(): void
    {
        $inactiveCustomer = Customer::factory()->create(['is_active' => false]);
        $inactiveRate = TaxRate::factory()->inactive()->create();

        $this->actingAs($this->financeOfficer)->post(route('quotations.store'), $this->payload([
            'customer_id' => $inactiveCustomer->id,
            'valid_until' => today()->subDay()->toDateString(),
            'document_discount_type' => 'percentage',
            'document_discount_value' => '150',
            'lines' => [
                ['description' => '', 'quantity' => '0', 'unit_price' => '-5', 'tax_rate_id' => $inactiveRate->id],
            ],
        ]))->assertSessionHasErrors([
            'customer_id', 'valid_until', 'document_discount_value',
            'lines.0.description', 'lines.0.quantity', 'lines.0.unit_price', 'lines.0.tax_rate_id',
        ]);

        $this->assertSame(0, Quotation::query()->count());
    }

    public function test_a_quotation_needs_at_least_one_line(): void
    {
        $this->actingAs($this->financeOfficer)
            ->post(route('quotations.store'), $this->payload(['lines' => [['description' => '', 'quantity' => '']]]))
            ->assertSessionHasErrors('lines');
    }

    public function test_a_line_discount_above_the_line_amount_is_refused_without_saving(): void
    {
        $this->actingAs($this->financeOfficer)
            ->from(route('quotations.create'))
            ->post(route('quotations.store'), $this->payload([
                'lines' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '1000', 'discount_amount' => '2000']],
            ]))
            ->assertRedirect(route('quotations.create'))
            ->assertSessionHas('error');

        $this->assertSame(0, Quotation::query()->count());
    }

    public function test_drafts_can_be_edited_but_finalised_quotations_cannot(): void
    {
        $quotation = app(QuotationService::class)->createDraft($this->payload(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)
            ->put(route('quotations.update', $quotation), $this->payload([
                'lines' => [['description' => 'Updated', 'quantity' => '1', 'unit_price' => '1000']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('1000.00', (string) $quotation->fresh()->total);

        app(QuotationService::class)->submit($quotation->fresh(), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->get(route('quotations.edit', $quotation))->assertForbidden();
        $this->actingAs($this->financeOfficer)->put(route('quotations.update', $quotation), $this->payload())->assertForbidden();
    }

    public function test_only_unnumbered_drafts_can_be_deleted(): void
    {
        $service = app(QuotationService::class);
        $draft = $service->createDraft($this->payload(), $this->financeOfficer);
        $finalised = $service->submit($service->createDraft($this->payload(), $this->financeOfficer), $this->financeOfficer);

        $this->actingAs($this->financeOfficer)->delete(route('quotations.destroy', $draft))->assertRedirect(route('quotations.index'));
        $this->assertSoftDeleted($draft);

        $this->actingAs($this->financeOfficer)->delete(route('quotations.destroy', $finalised))->assertForbidden();
        $this->assertNotSoftDeleted($finalised);
    }

    public function test_a_customer_with_quotations_cannot_be_deleted(): void
    {
        app(QuotationService::class)->createDraft($this->payload(), $this->financeOfficer);

        $this->assertTrue($this->customer->hasTransactions());
    }

    public function test_pages_render_and_list_can_be_filtered(): void
    {
        $service = app(QuotationService::class);
        $mine = $service->submit($service->createDraft($this->payload(), $this->financeOfficer), $this->financeOfficer);
        $otherCustomer = Customer::factory()->create(['name' => 'Zebra Traders']);
        $other = $service->createDraft($this->payload(['customer_id' => $otherCustomer->id]), $this->financeOfficer);

        $this->actingAs($this->financeOfficer);
        $this->get(route('quotations.create'))->assertOk();
        $this->get(route('quotations.show', $mine))->assertOk()->assertSee($mine->number);
        $this->get(route('customers.show', $this->customer))->assertOk()->assertSee($mine->number);

        $this->get(route('quotations.index', ['customer' => $this->customer->id]))
            ->assertOk()
            ->assertSee($mine->number)
            ->assertDontSee(route('quotations.show', $other));

        $this->get(route('quotations.index', ['search' => 'Zebra']))
            ->assertOk()
            ->assertSee(route('quotations.show', $other))
            ->assertDontSee(route('quotations.show', $mine));
    }
}
