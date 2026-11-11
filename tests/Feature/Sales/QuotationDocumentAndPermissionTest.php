<?php

namespace Tests\Feature\Sales;

use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Sales\QuotationService;
use App\Services\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Printed/PDF quotations and the default role matrix for quotations (docs/05 §2).
 */
class QuotationDocumentAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    private Quotation $quotation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $service = app(QuotationService::class);
        $this->quotation = $service->submit($service->createDraft([
            'customer_id' => Customer::factory()->create(['name' => 'Acme Uganda Ltd'])->id,
            'quote_date' => today()->toDateString(),
            'valid_until' => today()->addDays(30)->toDateString(),
            'terms' => 'Payment within 30 days.',
            'lines' => [['description' => 'Network cabling', 'quantity' => '3', 'unit_price' => '250000']],
        ], $this->financeOfficer), $this->financeOfficer);
    }

    public function test_the_pdf_download_is_a_valid_pdf(): void
    {
        $response = $this->actingAs($this->financeOfficer)->get(route('quotations.pdf', $this->quotation));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString($this->quotation->number, (string) $response->headers->get('content-disposition'));
    }

    public function test_the_print_view_shows_the_organization_customer_and_lines(): void
    {
        app(SettingsService::class)->update('organization', ['name' => 'Kampala Supplies Ltd', 'signatory_name' => 'Grace Namata']);

        $this->actingAs($this->financeOfficer)->get(route('quotations.print', $this->quotation))
            ->assertOk()
            ->assertSee('Kampala Supplies Ltd')
            ->assertSee('Acme Uganda Ltd')
            ->assertSee('Network cabling')
            ->assertSee('UGX 750,000')
            ->assertSee('Grace Namata')
            ->assertSee('Payment within 30 days.');
    }

    public function test_documents_require_permission_to_view_quotations(): void
    {
        $employee = $this->userWithRole(SystemRole::GeneralEmployee);

        $this->actingAs($employee)->get(route('quotations.pdf', $this->quotation))->assertForbidden();
        $this->actingAs($employee)->get(route('quotations.print', $this->quotation))->assertForbidden();
    }

    /**
     * @return array<string, array{SystemRole, array{view: bool, create: bool, approve: bool, send: bool, cancel: bool}}>
     */
    public static function roles(): array
    {
        $none = ['view' => false, 'create' => false, 'approve' => false, 'send' => false, 'cancel' => false];

        return [
            'manager' => [SystemRole::Manager, ['view' => true, 'create' => true, 'approve' => true, 'send' => true, 'cancel' => true]],
            'finance officer' => [SystemRole::FinanceOfficer, ['view' => true, 'create' => true, 'approve' => false, 'send' => true, 'cancel' => true]],
            'accountant' => [SystemRole::Accountant, [...$none, 'view' => true]],
            'inventory officer' => [SystemRole::InventoryOfficer, $none],
            'human resource officer' => [SystemRole::HumanResourceOfficer, $none],
            'general employee' => [SystemRole::GeneralEmployee, $none],
        ];
    }

    /**
     * @param  array{view: bool, create: bool, approve: bool, send: bool, cancel: bool}  $expected
     */
    #[DataProvider('roles')]
    public function test_role_has_the_expected_quotation_abilities(SystemRole $role, array $expected): void
    {
        $user = $this->userWithRole($role);

        $this->assertSame($expected, [
            'view' => $user->can('viewAny', Quotation::class),
            'create' => $user->can('create', Quotation::class),
            'approve' => $user->can('quotations.approve'),
            'send' => $user->can('markSent', $this->quotation),
            'cancel' => $user->can('cancel', $this->quotation),
        ]);

        $this->actingAs($user)->get(route('quotations.index'))->assertStatus($expected['view'] ? 200 : 403);
        $this->actingAs($user)->get(route('quotations.create'))->assertStatus($expected['create'] ? 200 : 403);
    }
}
