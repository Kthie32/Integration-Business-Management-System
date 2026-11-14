<?php

namespace Tests\Feature\Security;

use App\Enums\SystemRole;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Signs in as each seeded role and opens every module's entry pages (docs/05 §2).
 *
 * The expected result for each page is worked out from the permissions the role is seeded with, so the test
 * proves that the screens are guarded by the documented permissions and nothing else.
 */
class RolePageAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each page, and the permissions that open it: every group must be satisfied by holding any one of its permissions.
     *
     * @var array<string, list<list<string>>>
     */
    private const PAGES = [
        '/admin/users' => [['users.view']],
        '/admin/users/create' => [['users.create']],
        '/admin/roles' => [['roles.manage']],
        '/admin/roles/create' => [['roles.manage']],
        '/admin/audit-log' => [['audit.view']],
        '/approval-workflows' => [['approvals.configure']],
        '/settings' => [['settings.organization', 'settings.finance', 'settings.system']],
        '/settings/organization' => [['settings.organization']],
        '/settings/finance' => [['settings.finance']],
        '/settings/numbering' => [['settings.finance']],
        '/settings/taxes' => [['settings.finance']],
        '/settings/system' => [['settings.system']],
        '/customers' => [['customers.view']],
        '/customers/create' => [['customers.create']],
        '/suppliers' => [['suppliers.view']],
        '/suppliers/create' => [['suppliers.create']],
        '/quotations' => [['quotations.view']],
        '/quotations/create' => [['quotations.create']],
        '/invoices' => [['invoices.view']],
        '/invoices/create' => [['invoices.create']],
        '/payments' => [['payments.view']],
        '/receipts' => [['payments.view']],
        '/expenses' => [['expenses.view']],
        '/expenses/create' => [['expenses.create']],
        '/items' => [['items.view']],
        '/items/create' => [['items.manage']],
        '/stock/levels' => [['stock.view']],
        '/stock/movements' => [['stock.view']],
        '/stock/issue' => [['stock.issue']],
        '/stock/transfer' => [['stock.transfer']],
        '/goods-receipts/create' => [['stock.receive']],
        '/stock-adjustments/create' => [['stock.adjust']],
        '/employees/create' => [['employees.create']],
        '/departments' => [['departments.manage', 'employees.view', 'employees.view_department']],
        '/payroll-runs' => [['payroll.view']],
        '/payroll-runs/create' => [['payroll.prepare']],
        '/pay-components' => [['payroll.configure', 'payroll.prepare', 'payroll.view']],
        '/statutory-rates' => [['payroll.configure', 'payroll.prepare', 'payroll.view']],
        '/my-payslips' => [['payslips.view_own']],
        '/requisitions/create' => [['requisitions.create']],
        '/reports' => [['reports.financial', 'reports.sales', 'reports.inventory', 'reports.payroll', 'reports.requisitions']],
        '/reports/revenue' => [['reports.financial']],
        '/reports/revenue/pdf' => [['reports.financial'], ['reports.export']],
        '/reports/revenue/print' => [['reports.financial']],
        '/reports/quotations' => [['reports.sales']],
        '/reports/stock-balances' => [['reports.inventory']],
        '/reports/payroll-summary' => [['reports.payroll']],
        '/reports/requisitions' => [['reports.requisitions']],
        '/dashboard' => [],
        '/approvals' => [],
    ];

    /**
     * Pages that answer with something other than 200 when opened: the settings hub forwards to the first settings page the user may change.
     *
     * @var array<string, int>
     */
    private const OPENED = ['/settings' => 302];

    /**
     * @return array<string, array{SystemRole}>
     */
    public static function roles(): array
    {
        return collect(SystemRole::cases())->mapWithKeys(fn (SystemRole $role): array => [$role->value => [$role]])->all();
    }

    #[DataProvider('roles')]
    public function test_each_role_opens_exactly_the_pages_its_permissions_allow(SystemRole $role): void
    {
        $this->seedRolesAndPermissions();
        $user = $this->userWithRole($role);
        $granted = $role === SystemRole::SuperAdministrator ? PermissionCatalog::all() : $role->defaultPermissions();
        $unexpected = [];

        foreach (self::PAGES as $uri => $groups) {
            $allowed = collect($groups)->every(fn (array $anyOf): bool => array_intersect($anyOf, $granted) !== []);
            $status = $this->actingAs($user)->get($uri)->getStatusCode();

            if ($status !== ($allowed ? self::OPENED[$uri] ?? 200 : 403)) {
                $unexpected[] = "{$uri}: ".($allowed ? 'allowed' : 'refused')." expected, got {$status}";
            }
        }

        $this->assertSame([], $unexpected);
    }
}
