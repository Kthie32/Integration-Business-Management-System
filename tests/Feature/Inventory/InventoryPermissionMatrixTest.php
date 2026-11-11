<?php

namespace Tests\Feature\Inventory;

use App\Enums\SystemRole;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The default role matrix for inventory (docs/05 §2), asserted against the policies and permission gates.
 */
class InventoryPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{SystemRole, array<string, bool>}>
     */
    public static function roles(): array
    {
        $none = ['viewItems' => false, 'manageItems' => false, 'viewStock' => false, 'receive' => false, 'issue' => false, 'transfer' => false, 'adjust' => false, 'approveAdjustment' => false, 'manageWarehouses' => false];

        return [
            'inventory officer' => [SystemRole::InventoryOfficer, ['viewItems' => true, 'manageItems' => true, 'viewStock' => true, 'receive' => true, 'issue' => true, 'transfer' => true, 'adjust' => true, 'approveAdjustment' => false, 'manageWarehouses' => true]],
            'procurement officer' => [SystemRole::ProcurementOfficer, [...$none, 'viewItems' => true, 'viewStock' => true, 'receive' => true]],
            'manager' => [SystemRole::Manager, [...$none, 'viewItems' => true, 'viewStock' => true, 'approveAdjustment' => true]],
            'finance officer' => [SystemRole::FinanceOfficer, [...$none, 'viewItems' => true, 'viewStock' => true]],
            'department head' => [SystemRole::DepartmentHead, [...$none, 'viewItems' => true, 'viewStock' => true]],
            'human resource officer' => [SystemRole::HumanResourceOfficer, $none],
            'general employee' => [SystemRole::GeneralEmployee, $none],
        ];
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('roles')]
    public function test_role_has_the_expected_inventory_abilities(SystemRole $role, array $expected): void
    {
        $this->seedRolesAndPermissions();
        $user = $this->userWithRole($role);
        $adjustment = StockAdjustment::factory()->create();

        $actual = [
            'viewItems' => $user->can('viewAny', Item::class),
            'manageItems' => $user->can('create', Item::class),
            'viewStock' => $user->can('stock.view'),
            'receive' => $user->can('create', GoodsReceipt::class),
            'issue' => $user->can('stock.issue'),
            'transfer' => $user->can('stock.transfer'),
            'adjust' => $user->can('create', StockAdjustment::class),
            'approveAdjustment' => $user->can('decide', $adjustment),
            'manageWarehouses' => $user->can('create', Warehouse::class),
        ];

        $this->assertSame($expected, $actual);
    }
}
