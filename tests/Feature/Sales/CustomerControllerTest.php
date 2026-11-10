<?php

namespace Tests\Feature\Sales;

use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = User::factory()->withRole(SystemRole::FinanceOfficer)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'type' => 'company',
            'name' => 'Acme Uganda Ltd',
            'tin' => '1000123456',
            'contact_person' => 'Jane Akello',
            'phone' => '+256 772 000111',
            'email' => 'Accounts@Acme.UG',
            'payment_terms_days' => '14',
            'credit_limit' => '5,000,000',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_guest_is_redirected_to_sign_in(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_customer_permission_is_forbidden(): void
    {
        $storekeeper = User::factory()->withRole(SystemRole::InventoryOfficer)->create();

        $this->actingAs($storekeeper)->get(route('customers.index'))->assertForbidden();
    }

    public function test_view_only_user_cannot_create_customers(): void
    {
        $manager = User::factory()->withRole(SystemRole::Manager)->create();

        $this->actingAs($manager)->get(route('customers.index'))->assertOk();
        $this->actingAs($manager)->post(route('customers.store'), $this->validPayload())->assertForbidden();
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_creates_customer_with_sequential_code(): void
    {
        $response = $this->actingAs($this->financeOfficer)->post(route('customers.store'), $this->validPayload());

        $customer = Customer::query()->sole();
        $response->assertRedirect(route('customers.show', $customer));
        $this->assertSame('CUS-0001', $customer->code);
        $this->assertSame('accounts@acme.ug', $customer->email);
        $this->assertSame('5000000.00', $customer->credit_limit);
        $this->assertSame($this->financeOfficer->id, $customer->created_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => Customer::class, 'auditable_id' => $customer->id]);

        $this->actingAs($this->financeOfficer)->post(route('customers.store'), $this->validPayload(['name' => 'Second']));
        $this->assertSame('CUS-0002', Customer::query()->where('name', 'Second')->value('code'));
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('customers.store'), [])
            ->assertSessionHasErrors(['type', 'name', 'payment_terms_days']);
    }

    public function test_rejects_negative_credit_limit(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('customers.store'), $this->validPayload(['credit_limit' => '-1']))
            ->assertSessionHasErrors('credit_limit');
    }

    public function test_updates_and_deactivates_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->financeOfficer)
            ->put(route('customers.update', $customer), $this->validPayload(['name' => 'Renamed', 'is_active' => '0']))
            ->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertSame('Renamed', $customer->name);
        $this->assertFalse($customer->is_active);
    }

    public function test_search_filters_the_list(): void
    {
        Customer::factory()->create(['name' => 'Kampala Traders']);
        Customer::factory()->create(['name' => 'Gulu Supplies']);

        $this->actingAs($this->financeOfficer)->get(route('customers.index', ['search' => 'Kampala']))
            ->assertOk()
            ->assertSee('Kampala Traders')
            ->assertDontSee('Gulu Supplies');
    }

    public function test_customer_without_transactions_can_be_deleted(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->financeOfficer)->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted($customer);
    }

    public function test_user_without_delete_permission_cannot_delete(): void
    {
        $accountant = User::factory()->withRole(SystemRole::Accountant)->create();
        $customer = Customer::factory()->create();

        $this->actingAs($accountant)->delete(route('customers.destroy', $customer))->assertForbidden();
        $this->assertNotSoftDeleted($customer);
    }

    public function test_profile_page_shows_customer_details(): void
    {
        $customer = Customer::factory()->create(['name' => 'Mbarara Dairy', 'payment_terms_days' => 45]);

        $this->actingAs($this->financeOfficer)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Mbarara Dairy')
            ->assertSee('45 days');
    }
}
