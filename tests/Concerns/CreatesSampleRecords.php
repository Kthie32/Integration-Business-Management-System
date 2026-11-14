<?php

namespace Tests\Concerns;

use App\Enums\PayComponentKind;
use App\Enums\SystemRole;
use App\Models\ApprovalWorkflow;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Department;
use App\Models\EmployeeDocument;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PayComponent;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\StatutoryRate;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Warehouse;
use App\Notifications\SystemMessage;
use App\Services\Inventory\StockService;
use App\Services\Payroll\PayrollService;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\PaymentService;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Builds one record for every route parameter in the application, so a test can call any route with real,
 * bindable records and check what the authorization layer does with them.
 *
 * Requires roles and permissions to be seeded and uses the CreatesPayroll helpers.
 */
trait CreatesSampleRecords
{
    use CreatesPayroll;

    /**
     * @return array<string, int|string> route parameter name => value
     */
    protected function sampleRouteParameters(): array
    {
        $owner = $this->userWithRole(SystemRole::SuperAdministrator);
        $this->seedPayroll();

        $warehouse = Warehouse::factory()->default()->create();
        $item = Item::factory()->create();
        $movement = app(StockService::class)->receive($item, $warehouse, '20', '1000');

        $customer = Customer::factory()->create();
        $invoices = app(InvoiceService::class);
        $issued = $invoices->issue($invoices->createDraft([
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => today()->toDateString(),
            'lines' => [['description' => 'Consulting', 'quantity' => '1', 'unit_price' => '100000']],
        ], $owner), $owner);
        $payment = app(PaymentService::class)->record($issued, [
            'payment_date' => today()->toDateString(), 'amount' => '40000', 'method' => 'cash', 'reference' => null,
        ], $owner);

        $employee = $this->employeeWithSalary('1500000');
        $run = app(PayrollService::class)->calculate($this->draftRun($owner), $owner);
        $adjustment = app(PayrollService::class)->addAdjustment($run, [
            'employee_id' => $employee->id, 'kind' => PayComponentKind::Allowance->value, 'description' => 'Overtime', 'amount' => '10000',
        ], $owner);

        $document = EmployeeDocument::factory()->create(['employee_id' => $employee->id]);
        $owner->notify(new SystemMessage('Sample', 'A sample notification.'));
        $role = Role::query()->create(['name' => 'Sample custom role', 'guard_name' => 'web', 'is_system' => false]);

        return [
            'session' => Str::random(40),
            'auditLog' => AuditLog::query()->latest('id')->value('id'),
            'role' => $role->id,
            'user' => $owner->id,
            'approval_workflow' => ApprovalWorkflow::factory()->create()->id,
            'customer' => $customer->id,
            'department' => Department::factory()->create()->id,
            'employee' => $employee->id,
            'document' => $document->id,
            'structure' => $employee->salaryStructures()->value('id'),
            'expense_category' => ExpenseCategory::factory()->create()->id,
            'expense' => Expense::factory()->create()->id,
            'goods_receipt' => GoodsReceipt::factory()->create()->id,
            'invoice' => Invoice::factory()->create()->id,
            'item_category' => ItemCategory::factory()->create()->id,
            'item' => $item->id,
            'notification' => $owner->notifications()->value('id'),
            'payComponent' => PayComponent::factory()->create()->id,
            'payment' => $payment->id,
            'payrollRun' => $run->id,
            'adjustment' => $adjustment->id,
            'payslip' => $run->items()->value('id'),
            'quotation' => Quotation::factory()->create()->id,
            'receipt' => $payment->receipt->id,
            'requisition' => Requisition::factory()->create()->id,
            'tax_rate' => TaxRate::factory()->create()->id,
            'statutoryRate' => StatutoryRate::query()->value('id'),
            'stock_adjustment' => StockAdjustment::factory()->create()->id,
            'movement' => $movement->id,
            'supplier' => Supplier::factory()->create()->id,
            'warehouse' => $warehouse->id,
            'documentType' => 'invoice',
            'operation' => 'issue',
            'format' => 'pdf',
        ];
    }

    /**
     * The URI of a route with every parameter filled from the sample records.
     *
     * @param  array<string, int|string>  $parameters
     */
    protected function sampleUri(Route $route, array $parameters): string
    {
        $uri = $route->uri();

        // Report keys differ between the report hub and payroll run exports.
        $parameters['report'] = str_starts_with($uri, 'payroll-runs/') ? 'register' : 'revenue';

        return '/'.preg_replace_callback('/\{(\w+)\??\}/', function (array $match) use ($parameters, $uri): string {
            if (! array_key_exists($match[1], $parameters)) {
                throw new \LogicException("No sample value for route parameter {{$match[1]}} in {$uri}.");
            }

            return (string) $parameters[$match[1]];
        }, $uri);
    }

    /**
     * Every application route behind the two-factor requirement (the signed-in part of the system).
     *
     * @return list<Route>
     */
    protected function protectedRoutes(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => in_array('two-factor.enforced', $route->gatherMiddleware(), true))
            ->values()
            ->all();
    }
}
