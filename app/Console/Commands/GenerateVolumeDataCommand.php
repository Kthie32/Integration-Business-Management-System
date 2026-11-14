<?php

namespace App\Console\Commands;

use App\Enums\ApprovalDecision;
use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Finance\ExpenseService;
use App\Services\HR\DepartmentService;
use App\Services\HR\EmployeeService;
use App\Services\Inventory\ItemService;
use App\Services\Inventory\StockService;
use App\Services\Parties\CustomerService;
use App\Services\Parties\SupplierService;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\SalaryStructureService;
use App\Services\Requisition\RequisitionService;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\PaymentService;
use App\Services\Sales\QuotationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Fills a non-production database with a year of realistic activity, for checking the speed of the dashboard,
 * list screens and reports at volume (Phase 14). Everything goes through the normal services, so numbering,
 * stock movements, receipts, approvals and audit entries are created exactly as they are in daily use.
 *
 * Not part of normal seeding. Run it on a copy of the database that has been migrated and seeded.
 */
#[Signature('ibms:demo:volume
    {--invoices=2000 : Issued invoices to create}
    {--employees=60 : Employees on the payroll}
    {--months=12 : Months of history (payroll runs and document dates)}
    {--force : Run without asking for confirmation}')]
#[Description('Generate a year of sample activity for performance checks (never in production)')]
class GenerateVolumeDataCommand extends Command
{
    private const ITEMS = 150;

    private const CUSTOMERS = 300;

    private const SUPPLIERS = 40;

    private const DEPARTMENTS = 8;

    /**
     * Each kind of document per 1,000 invoices.
     */
    private const QUOTATIONS_PER_THOUSAND = 250;

    private const EXPENSES_PER_THOUSAND = 300;

    private const REQUISITIONS_PER_THOUSAND = 150;

    private User $finance;

    private User $hr;

    private CarbonImmutable $start;

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Sample data is never generated in production.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This adds thousands of sample records to the '.DB::connection()->getDatabaseName().' database. Continue?')) {
            return self::FAILURE;
        }

        mt_srand(14);
        Notification::fake();
        $invoiceCount = max(1, (int) $this->option('invoices'));
        $this->start = CarbonImmutable::today()->startOfMonth()->subMonths(max(1, (int) $this->option('months')) - 1);
        $this->finance = $this->actor('volume.finance@example.test', 'Volume Finance', SystemRole::FinanceOfficer);
        $this->hr = $this->actor('volume.hr@example.test', 'Volume HR', SystemRole::HumanResourceOfficer);
        Auth::setUser($this->finance);

        $warehouse = Warehouse::default() ?? Warehouse::query()->firstOrFail();
        $departments = $this->step('Departments', fn (): array => $this->departments());
        $items = $this->step('Items and opening stock', fn (): array => $this->items($warehouse, $invoiceCount));
        $customers = $this->step('Customers', fn (): array => $this->customers());
        $this->step('Suppliers', fn () => $this->suppliers());
        $this->step('Quotations', fn () => $this->quotations($customers, $items, intdiv($invoiceCount * self::QUOTATIONS_PER_THOUSAND, 1000)));
        $this->step('Invoices and payments', fn () => $this->invoices($customers, $items, $warehouse, $invoiceCount));
        $this->step('Expenses', fn () => $this->expenses($departments, intdiv($invoiceCount * self::EXPENSES_PER_THOUSAND, 1000)));
        $this->step('Requisitions', fn () => $this->requisitions($departments, $items, intdiv($invoiceCount * self::REQUISITIONS_PER_THOUSAND, 1000)));
        $this->step('Employees and payroll', fn () => $this->payroll($departments, max(1, (int) $this->option('employees'))));

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function step(string $label, callable $callback): mixed
    {
        $started = microtime(true);
        $result = $callback();
        $this->line(sprintf('  %-26s %6.1fs', $label, microtime(true) - $started));

        return $result;
    }

    private function actor(string $email, string $name, SystemRole $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill(['name' => $name, 'password' => bcrypt(bin2hex(random_bytes(16))), 'is_active' => true])->save();
        $user->syncRoles([$role->value]);

        return $user;
    }

    /**
     * @return list<int>
     */
    private function departments(): array
    {
        $service = app(DepartmentService::class);
        $ids = [];

        for ($i = 1; $i <= self::DEPARTMENTS; $i++) {
            $code = sprintf('VOL-%02d', $i);
            $ids[] = Department::query()->where('code', $code)->value('id')
                ?? $service->create(['code' => $code, 'name' => "Volume department {$i}", 'is_active' => true])->id;
        }

        return $ids;
    }

    /**
     * @return list<Item>
     */
    private function items(Warehouse $warehouse, int $invoiceCount): array
    {
        $service = app(ItemService::class);
        $stock = app(StockService::class);
        $openingQuantity = (string) max(100, intdiv($invoiceCount * 3, self::ITEMS) * 4);
        $items = [];

        for ($i = 1; $i <= self::ITEMS; $i++) {
            $cost = mt_rand(5, 400) * 1000;
            $item = $service->create([
                'name' => "Volume item {$i}", 'type' => 'stock', 'unit' => 'pcs',
                'selling_price' => (string) ($cost * 1.4), 'cost_price' => (string) $cost, 'min_stock_level' => '0', 'is_active' => true,
            ], $this->finance);
            $stock->recordOpeningBalance($item, $warehouse, $openingQuantity, (string) $cost, 'Volume test opening stock', $this->start->subDay());
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return list<int>
     */
    private function customers(): array
    {
        $service = app(CustomerService::class);

        return array_map(fn (int $i): int => $service->create([
            'type' => $i % 3 === 0 ? 'individual' : 'company', 'name' => "Volume customer {$i}", 'payment_terms_days' => [0, 14, 30][$i % 3],
        ], $this->finance)->id, range(1, self::CUSTOMERS));
    }

    private function suppliers(): void
    {
        $service = app(SupplierService::class);

        for ($i = 1; $i <= self::SUPPLIERS; $i++) {
            $service->create(['name' => "Volume supplier {$i}", 'payment_terms_days' => 30], $this->finance);
        }
    }

    /**
     * @param  list<int>  $customers
     * @param  list<Item>  $items
     */
    private function quotations(array $customers, array $items, int $count): void
    {
        $service = app(QuotationService::class);

        for ($i = 0; $i < $count; $i++) {
            $date = $this->randomDate();
            $quotation = $service->createDraft([
                'customer_id' => $customers[array_rand($customers)],
                'quote_date' => $date->toDateString(),
                'valid_until' => $date->addDays(30)->toDateString(),
                'lines' => $this->lines($items),
            ], $this->finance);

            if ($i % 4 !== 0) {
                $service->submit($quotation, $this->finance);
            }
        }
    }

    /**
     * @param  list<int>  $customers
     * @param  list<Item>  $items
     */
    private function invoices(array $customers, array $items, Warehouse $warehouse, int $count): void
    {
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);
        $bar = $this->output->createProgressBar($count);

        for ($i = 0; $i < $count; $i++) {
            $date = $this->randomDate();
            $invoice = $invoices->issue($invoices->createDraft([
                'customer_id' => $customers[array_rand($customers)],
                'warehouse_id' => $warehouse->id,
                'invoice_date' => $date->toDateString(),
                'lines' => $this->lines($items),
            ], $this->finance), $this->finance);

            $roll = mt_rand(1, 10);

            if ($roll <= 7) {
                $amount = $roll <= 5 ? (string) $invoice->balance_due : bcdiv((string) $invoice->balance_due, '2', 2);
                $paidOn = $date->addDays(mt_rand(0, 20));
                $payments->record($invoice, [
                    'payment_date' => ($paidOn->isFuture() ? CarbonImmutable::today() : $paidOn)->toDateString(),
                    'amount' => $amount,
                    'method' => ['cash', 'bank_transfer', 'mobile_money'][$i % 3],
                    'reference' => $i % 3 === 0 ? null : 'VOL-'.$i,
                ], $this->finance);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * @param  list<int>  $departments
     */
    private function expenses(array $departments, int $count): void
    {
        $service = app(ExpenseService::class);
        $categories = ExpenseCategory::query()->where('is_active', true)->pluck('id')->all();

        for ($i = 0; $i < $count; $i++) {
            $expense = $service->createDraft([
                'expense_date' => $this->randomDate()->toDateString(),
                'expense_category_id' => $categories[array_rand($categories)],
                'department_id' => $departments[array_rand($departments)],
                'description' => "Volume expense {$i}",
                'amount' => (string) (mt_rand(10, 2000) * 1000),
                'payment_method' => 'cash',
            ], null, $this->finance);

            if ($i % 5 !== 0) {
                $service->record($expense, $this->finance);
            }
        }
    }

    /**
     * Draft requisitions, so the approval route does not depend on who heads each sample department.
     *
     * @param  list<int>  $departments
     * @param  list<Item>  $items
     */
    private function requisitions(array $departments, array $items, int $count): void
    {
        $service = app(RequisitionService::class);

        for ($i = 0; $i < $count; $i++) {
            $item = $items[array_rand($items)];
            $service->createDraft([
                'department_id' => $departments[array_rand($departments)],
                'priority' => 'normal',
                'purpose' => "Volume requisition {$i}",
                'lines' => [['item_id' => $item->id, 'quantity' => (string) mt_rand(1, 10)]],
            ], null, $this->finance);
        }
    }

    /**
     * Employees with salaries, and one approved and paid run per month.
     *
     * @param  list<int>  $departments
     */
    private function payroll(array $departments, int $employeeCount): void
    {
        $employees = app(EmployeeService::class);
        $structures = app(SalaryStructureService::class);
        $payroll = app(PayrollService::class);

        for ($i = 1; $i <= $employeeCount; $i++) {
            $employee = $employees->create([
                'first_name' => 'Volume', 'last_name' => "Employee {$i}",
                'department_id' => $departments[$i % count($departments)], 'job_title' => 'Officer',
                'employment_type' => 'permanent', 'hire_date' => $this->start->subYear()->toDateString(), 'is_resident_for_tax' => true,
            ], $this->hr);
            $structures->create($employee, [
                'basic_salary' => (string) (mt_rand(6, 60) * 100000), 'effective_from' => $this->start->subYear()->toDateString(), 'components' => [],
            ], $this->hr);
        }

        for ($month = $this->start; $month->lessThan(CarbonImmutable::today()->startOfMonth()); $month = $month->addMonth()) {
            try {
                $run = $payroll->create(['period_year' => $month->year, 'period_month' => $month->month], $this->hr);
            } catch (BusinessRuleException $exception) {
                $this->warn("  Skipped {$month->format('F Y')}: {$exception->getMessage()}");

                continue;
            }

            $run = $payroll->submit($payroll->calculate($run, $this->hr), $this->hr);
            $run = $payroll->decide($run, $this->finance, ApprovalDecision::Approve);
            $payroll->markPaid($run, $this->finance, CarbonImmutable::today()->toDateString(), 'VOL-'.$month->format('Ym'));
        }
    }

    /**
     * @param  list<Item>  $items
     * @return list<array<string, string|int>>
     */
    private function lines(array $items): array
    {
        $lines = [];

        foreach ((array) array_rand($items, mt_rand(1, 4)) as $index) {
            $item = $items[$index];
            $lines[] = ['item_id' => $item->id, 'description' => $item->name, 'quantity' => (string) mt_rand(1, 3), 'unit_price' => (string) $item->selling_price];
        }

        return $lines;
    }

    private function randomDate(): CarbonImmutable
    {
        $days = (int) $this->start->diffInDays(CarbonImmutable::today());

        return $this->start->addDays(mt_rand(0, $days));
    }
}
