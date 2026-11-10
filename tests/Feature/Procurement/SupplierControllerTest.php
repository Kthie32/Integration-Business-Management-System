<?php

namespace Tests\Feature\Procurement;

use App\Enums\SystemRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $procurementOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->procurementOfficer = User::factory()->withRole(SystemRole::ProcurementOfficer)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Nile Hardware',
            'phone' => '0414 123456',
            'bank_name' => 'Centenary Bank',
            'bank_account' => '3100012345',
            'mobile_money_number' => '+256 701 222333',
            'payment_terms_days' => '30',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_user_without_supplier_permission_is_forbidden(): void
    {
        $hrOfficer = User::factory()->withRole(SystemRole::HumanResourceOfficer)->create();

        $this->actingAs($hrOfficer)->get(route('suppliers.index'))->assertForbidden();
    }

    public function test_view_only_user_cannot_edit_suppliers(): void
    {
        $accountant = User::factory()->withRole(SystemRole::Accountant)->create();
        $supplier = Supplier::factory()->create();

        $this->actingAs($accountant)->get(route('suppliers.show', $supplier))->assertOk();
        $this->actingAs($accountant)->put(route('suppliers.update', $supplier), $this->validPayload())->assertForbidden();
    }

    public function test_creates_supplier_with_sequential_code(): void
    {
        $response = $this->actingAs($this->procurementOfficer)->post(route('suppliers.store'), $this->validPayload());

        $supplier = Supplier::query()->sole();
        $response->assertRedirect(route('suppliers.show', $supplier));
        $this->assertSame('SUP-0001', $supplier->code);
        $this->assertSame('3100012345', $supplier->bank_account);
    }

    public function test_rejects_invalid_bank_account_characters(): void
    {
        $this->actingAs($this->procurementOfficer)->post(route('suppliers.store'), $this->validPayload(['bank_account' => '12<script>']))
            ->assertSessionHasErrors('bank_account');
    }

    public function test_updates_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->procurementOfficer)->put(route('suppliers.update', $supplier), $this->validPayload(['name' => 'Renamed', 'is_active' => '0']))
            ->assertRedirect(route('suppliers.show', $supplier));

        $this->assertSame('Renamed', $supplier->fresh()->name);
        $this->assertFalse($supplier->fresh()->is_active);
    }

    public function test_deletes_supplier_without_transactions(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->procurementOfficer)->delete(route('suppliers.destroy', $supplier))->assertRedirect(route('suppliers.index'));

        $this->assertSoftDeleted($supplier);
    }
}
