<?php

namespace Tests\Feature\Sales;

use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\SystemMessage;
use App\Services\Sales\InvoiceService;
use App\Services\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Printed/PDF invoices, the overdue reminder, dashboard figures and the default role matrix (docs/05 §2).
 */
class InvoiceDocumentAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $service = app(InvoiceService::class);
        $this->invoice = $service->issue($service->createDraft([
            'customer_id' => Customer::factory()->create(['name' => 'Acme Uganda Ltd'])->id,
            'invoice_date' => today()->subDays(40)->toDateString(),
            'due_date' => today()->subDays(10)->toDateString(),
            'terms' => 'Pay to our Stanbic account.',
            'lines' => [['description' => 'Network cabling', 'quantity' => '3', 'unit_price' => '250000']],
        ], $this->financeOfficer), $this->financeOfficer);
    }

    public function test_the_pdf_download_is_a_valid_pdf_and_is_audited(): void
    {
        $response = $this->actingAs($this->financeOfficer)->get(route('invoices.pdf', $this->invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString($this->invoice->number, (string) $response->headers->get('content-disposition'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'pdf_downloaded', 'auditable_id' => $this->invoice->id, 'auditable_type' => $this->invoice->getMorphClass()]);
    }

    public function test_the_print_view_shows_the_organization_customer_lines_and_balance(): void
    {
        app(SettingsService::class)->update('organization', ['name' => 'Kampala Supplies Ltd']);

        $this->actingAs($this->financeOfficer)->get(route('invoices.print', $this->invoice))
            ->assertOk()
            ->assertSee('Kampala Supplies Ltd')
            ->assertSee('Acme Uganda Ltd')
            ->assertSee('Network cabling')
            ->assertSee('Balance due')
            ->assertSee('UGX 750,000')
            ->assertSee('Pay to our Stanbic account.');
    }

    public function test_the_overdue_check_notifies_once_per_invoice(): void
    {
        Notification::fake();
        $accountant = $this->userWithRole(SystemRole::Accountant);
        $employee = $this->userWithRole(SystemRole::GeneralEmployee);

        $this->artisan('ibms:invoices:overdue-check')->expectsOutputToContain('1 invoice(s)')->assertSuccessful();

        Notification::assertSentTo([$this->financeOfficer, $accountant], SystemMessage::class);
        Notification::assertNotSentTo($employee, SystemMessage::class);
        $this->assertNotNull($this->invoice->fresh()->overdue_notified_at);

        $this->artisan('ibms:invoices:overdue-check')->expectsOutputToContain('0 invoice(s)')->assertSuccessful();
        Notification::assertSentToTimes($this->financeOfficer, SystemMessage::class, 1);
    }

    public function test_a_paid_invoice_is_not_overdue(): void
    {
        $this->invoice->forceFill(['balance_due' => '0', 'amount_paid' => (string) $this->invoice->total, 'status' => 'paid'])->save();

        $this->assertFalse($this->invoice->fresh()->isOverdue());
        $this->assertSame(0, Invoice::query()->overdue()->count());
    }

    public function test_the_dashboard_shows_receivables_and_overdue_invoices_to_finance(): void
    {
        $this->actingAs($this->financeOfficer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Outstanding receivables')
            ->assertSee('Overdue invoices')
            ->assertSee('Collected this month');

        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Outstanding receivables');
    }

    public function test_the_customer_page_lists_invoices_and_the_outstanding_balance(): void
    {
        $this->actingAs($this->financeOfficer)->get(route('customers.show', $this->invoice->customer))
            ->assertOk()
            ->assertSee($this->invoice->number)
            ->assertSee('Outstanding balance');
    }

    public function test_a_customer_with_invoices_cannot_be_deleted(): void
    {
        $customer = $this->invoice->customer;

        $this->actingAs($this->financeOfficer)->delete(route('customers.destroy', $customer));

        $this->assertNotSoftDeleted($customer);
    }

    /**
     * @return array<string, array{SystemRole, array{view: bool, create: bool, issue: bool, void: bool, viewPayments: bool, record: bool, reverse: bool}}>
     */
    public static function roles(): array
    {
        $none = ['view' => false, 'create' => false, 'issue' => false, 'void' => false, 'viewPayments' => false, 'record' => false, 'reverse' => false];

        return [
            'finance officer' => [SystemRole::FinanceOfficer, ['view' => true, 'create' => true, 'issue' => true, 'void' => true, 'viewPayments' => true, 'record' => true, 'reverse' => true]],
            'accountant' => [SystemRole::Accountant, ['view' => true, 'create' => true, 'issue' => true, 'void' => false, 'viewPayments' => true, 'record' => true, 'reverse' => false]],
            'manager' => [SystemRole::Manager, [...$none, 'view' => true, 'viewPayments' => true]],
            'inventory officer' => [SystemRole::InventoryOfficer, $none],
            'human resource officer' => [SystemRole::HumanResourceOfficer, $none],
            'general employee' => [SystemRole::GeneralEmployee, $none],
        ];
    }

    /**
     * @param  array{view: bool, create: bool, issue: bool, void: bool, viewPayments: bool, record: bool, reverse: bool}  $expected
     */
    #[DataProvider('roles')]
    public function test_role_has_the_expected_invoice_and_payment_abilities(SystemRole $role, array $expected): void
    {
        $user = $this->userWithRole($role);

        $this->assertSame($expected, [
            'view' => $user->can('viewAny', Invoice::class),
            'create' => $user->can('create', Invoice::class),
            'issue' => $user->can('invoices.issue'),
            'void' => $user->can('void', $this->invoice),
            'viewPayments' => $user->can('viewAny', Payment::class),
            'record' => $user->can('recordPayment', $this->invoice),
            'reverse' => $user->can('payments.reverse'),
        ]);

        $this->actingAs($user)->get(route('invoices.index'))->assertStatus($expected['view'] ? 200 : 403);
        $this->actingAs($user)->get(route('invoices.create'))->assertStatus($expected['create'] ? 200 : 403);
        $this->actingAs($user)->get(route('invoices.pdf', $this->invoice))->assertStatus($expected['view'] ? 200 : 403);
        $this->actingAs($user)->get(route('payments.index'))->assertStatus($expected['viewPayments'] ? 200 : 403);
    }
}
