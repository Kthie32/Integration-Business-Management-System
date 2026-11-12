<?php

namespace App\Services\Reports;

use App\Enums\EmployeeStatus;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Approval\ApprovalInbox;
use App\Services\System\SettingsService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * The permission-aware dashboard (FR-DSH-01…05): summary cards for the selected period, charts and recent
 * transactions. Every widget checks its own permissions, so a user only sees figures their role may see.
 */
class DashboardService
{
    public const PERIODS = [
        'month' => 'This month',
        'quarter' => 'This quarter',
        'year' => 'This year',
        'custom' => 'Custom',
    ];

    public function __construct(
        private SettingsService $settings,
        private ApprovalInbox $inbox,
        private PayrollCosts $payrollCosts,
    ) {}

    /**
     * Resolve the period selector (FR-DSH-05). Invalid or missing custom dates fall back to this month.
     *
     * @return array{key: string, from: CarbonImmutable, to: CarbonImmutable, phrase: string}
     */
    public function period(?string $key, ?string $from, ?string $to): array
    {
        $today = CarbonImmutable::today();

        $parse = function (?string $value): ?CarbonImmutable {
            if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                return null;
            }

            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

            return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
        };

        if ($key === 'custom') {
            $start = $parse($from);
            $end = $parse($to);

            if ($start !== null && $end !== null && $start->lessThanOrEqualTo($end)) {
                return ['key' => 'custom', 'from' => $start, 'to' => $end, 'phrase' => 'in period'];
            }
        }

        return match ($key) {
            'quarter' => ['key' => 'quarter', 'from' => $today->startOfQuarter(), 'to' => $today, 'phrase' => 'this quarter'],
            'year' => ['key' => 'year', 'from' => $today->startOfYear(), 'to' => $today, 'phrase' => 'this year'],
            default => ['key' => 'month', 'from' => $today->startOfMonth(), 'to' => $today, 'phrase' => 'this month'],
        };
    }

    /**
     * @param  array{key: string, from: CarbonImmutable, to: CarbonImmutable, phrase: string}  $period
     * @return array{cards: list<array<string, mixed>>, charts: list<array{title: string, chart: array<string, mixed>, url: string|null}>, recent: list<array<string, mixed>>}
     */
    public function build(User $user, array $period): array
    {
        return [
            'cards' => $this->cards($user, $period),
            'charts' => $this->charts($user, $period),
            'recent' => $this->recent($user),
        ];
    }

    /**
     * @param  array{key: string, from: CarbonImmutable, to: CarbonImmutable, phrase: string}  $period
     * @return list<array<string, mixed>>
     */
    private function cards(User $user, array $period): array
    {
        $dates = [$period['from']->toDateString(), $period['to']->toDateString()];
        $financials = $user->can('dashboard.view_financials');
        $phrase = $period['phrase'];
        $cards = [];

        if ($user->can('quotations.view')) {
            $created = Quotation::query()->whereNotNull('number')->where('status', '!=', QuotationStatus::Superseded->value)
                ->whereBetween('quote_date', $dates)
                ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

            $cards[] = $this->card('Open quotations', number_format(Quotation::query()
                ->whereIn('status', [QuotationStatus::PendingApproval->value, QuotationStatus::Approved->value, QuotationStatus::Sent->value])
                ->count()), 'file-earmark-text', 'primary', route('quotations.index'),
                number_format((int) $created->sum()).' created '.$phrase.' · '.number_format((int) ($created[QuotationStatus::Accepted->value] ?? 0)).' accepted');
        }

        if ($user->can('invoices.view')) {
            $issued = Invoice::query()->posted()->whereBetween('invoice_date', $dates)->selectRaw('COUNT(*) as invoices, COALESCE(SUM(total), 0) as value')->first();

            $cards[] = $this->card('Invoices issued '.$phrase, number_format((int) $issued->getAttribute('invoices')), 'receipt', 'primary',
                route('invoices.index'), $financials ? $this->settings->formatMoney((string) $issued->getAttribute('value')).' invoiced' : null);
        }

        if ($user->can('payments.view') && $financials) {
            $cards[] = $this->card('Collected '.$phrase, $this->settings->formatMoney((string) (Payment::query()->completed()
                ->whereBetween('payment_date', $dates)->sum('amount') ?: '0')), 'cash-coin', 'success',
                route('payments.index', ['from' => $dates[0], 'to' => $dates[1]]));
        }

        if ($user->can('invoices.view')) {
            if ($financials) {
                $cards[] = $this->card('Outstanding receivables', $this->settings->formatMoney((string) (Invoice::query()->open()->sum('balance_due') ?: '0')),
                    'wallet2', 'primary', route('invoices.index', ['status' => 'issued']));
            }

            $overdue = Invoice::query()->overdue()->count();
            $cards[] = $this->card('Overdue invoices', number_format($overdue), 'exclamation-triangle', $overdue > 0 ? 'danger' : 'secondary',
                route('invoices.index', ['status' => 'overdue']));
        }

        if ($user->can('expenses.view') && $financials) {
            $cards[] = $this->card('Expenses '.$phrase, $this->settings->formatMoney((string) (Expense::query()->recorded()
                ->whereBetween('expense_date', $dates)->sum('amount') ?: '0')), 'wallet', 'danger',
                route('expenses.index', ['status' => 'recorded', 'from' => $dates[0], 'to' => $dates[1]]), 'Payroll is shown separately');
        }

        if ($user->can('stock.view')) {
            $stock = StockLevel::query()->where('quantity', '>', 0)
                ->selectRaw('COUNT(DISTINCT item_id) as items, COALESCE(SUM(quantity * average_cost), 0) as value')->first();

            $cards[] = $this->card('Stock value', $this->settings->formatMoney(Money::of((string) $stock->getAttribute('value'))->toDecimal()),
                'boxes', 'info', route('stock.levels'), number_format((int) $stock->getAttribute('items')).' items in stock');
        }

        if ($user->can('items.view') || $user->can('stock.view')) {
            $low = Item::query()->active()->lowStock()->count();
            $cards[] = $this->card('Low-stock items', number_format($low), 'exclamation-diamond', $low > 0 ? 'warning' : 'secondary',
                $user->can('items.view') ? route('items.index', ['stock' => 'low']) : null);
        }

        if ($user->can('viewAny', Employee::class)) {
            $cards[] = $this->card('Active employees', number_format(Employee::query()->visibleTo($user)->where('status', EmployeeStatus::Active->value)->count()),
                'person-badge', 'info', route('employees.index'));
        }

        if ($user->can('payroll.view') && $user->can('dashboard.view_payroll')) {
            $lastPayroll = PayrollRun::query()->released()->orderByDesc('period_year')->orderByDesc('period_month')->first();
            $cards[] = $this->card('Last payroll (net pay)', $lastPayroll === null ? 'None yet' : $this->settings->formatMoney((string) $lastPayroll->total_net),
                'cash-stack', 'primary', $lastPayroll === null ? route('payroll-runs.index') : route('payroll-runs.show', $lastPayroll),
                $lastPayroll === null ? null : $lastPayroll->periodLabel().' · '.$lastPayroll->status->label());
        }

        if ($user->can('viewAny', Requisition::class)) {
            $cards[] = $this->card('Requisitions in approval', number_format(Requisition::query()->visibleTo($user)->pendingApproval()->count()),
                'clipboard-check', 'info', route('requisitions.index', ['status' => 'pending']));
        }

        $approvals = $this->inbox->countFor($user);
        $cards[] = $this->card('Awaiting my approval', number_format($approvals), 'inbox', $approvals > 0 ? 'warning' : 'secondary', route('approvals.inbox'));

        if ($user->can('users.view')) {
            $cards[] = $this->card('Active users', number_format(User::query()->active()->count()), 'people', 'primary', route('admin.users.index', ['status' => 'active']));
        }

        return $cards;
    }

    /**
     * @param  array{key: string, from: CarbonImmutable, to: CarbonImmutable, phrase: string}  $period
     * @return list<array{title: string, chart: array<string, mixed>, url: string|null}>
     */
    private function charts(User $user, array $period): array
    {
        $charts = [];
        $financials = $user->can('dashboard.view_financials');
        $dates = [$period['from']->toDateString(), $period['to']->toDateString()];

        if ($financials && ($user->can('payments.view') || $user->can('expenses.view'))) {
            $charts[] = ['title' => 'Revenue vs expenses – last 12 months', 'chart' => $this->revenueVsExpenses($user),
                'url' => $user->can('reports.financial') ? route('reports.show', 'revenue-vs-expenses') : null];
        }

        if ($user->can('invoices.view')) {
            $statuses = Invoice::query()->where('status', '!=', InvoiceStatus::Draft->value)->whereBetween('invoice_date', $dates)
                ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get();

            $charts[] = ['title' => 'Invoices by status – '.$period['phrase'], 'chart' => [
                'type' => 'doughnut',
                'labels' => $statuses->map(fn (Invoice $row): string => $row->status->label())->all(),
                'datasets' => [['label' => 'Invoices', 'data' => $statuses->map(fn (Invoice $row): int => (int) $row->getAttribute('aggregate'))->all()]],
                'money' => false,
            ], 'url' => $user->can('reports.financial') ? route('reports.show', ['invoice-register', 'from' => $dates[0], 'to' => $dates[1]]) : null];
        }

        if ($user->can('invoices.view') && $financials) {
            $top = DB::table('invoices')->join('customers', 'customers.id', '=', 'invoices.customer_id')
                ->whereNull('invoices.deleted_at')->whereIn('invoices.status', InvoiceStatus::postedValues())
                ->whereBetween('invoices.invoice_date', $dates)
                ->groupBy('customers.id', 'customers.name')->selectRaw('customers.name, SUM(invoices.total) as total')
                ->orderByDesc('total')->limit(5)->get();

            $charts[] = ['title' => 'Top customers – '.$period['phrase'], 'chart' => [
                'type' => 'hbar',
                'labels' => $top->pluck('name')->all(),
                'datasets' => [['label' => 'Invoiced', 'data' => $top->map(fn (object $row): float => (float) $row->total)->all()]],
                'money' => true,
            ], 'url' => $user->can('reports.sales') ? route('reports.show', ['sales-by-customer', 'from' => $dates[0], 'to' => $dates[1]]) : null];
        }

        if ($user->can('payroll.view') && $user->can('dashboard.view_payroll')) {
            $runs = PayrollRun::query()->released()->orderByDesc('period_year')->orderByDesc('period_month')->limit(12)->get()->reverse()->values();

            $charts[] = ['title' => 'Payroll trend – last 12 runs', 'chart' => [
                'type' => 'line',
                'labels' => $runs->map(fn (PayrollRun $run): string => $run->periodStart()->format('M Y'))->all(),
                'datasets' => [
                    ['label' => 'Gross pay', 'data' => $runs->map(fn (PayrollRun $run): float => (float) $run->total_gross)->all()],
                    ['label' => 'Net pay', 'data' => $runs->map(fn (PayrollRun $run): float => (float) $run->total_net)->all()],
                ],
                'money' => true,
            ], 'url' => $user->can('reports.payroll') ? route('reports.show', 'payroll-summary') : null];
        }

        return $charts;
    }

    /**
     * Monthly revenue against expenses for the last 12 months. Payroll is added to expenses only for users who may
     * see payroll figures.
     *
     * @return array<string, mixed>
     */
    private function revenueVsExpenses(User $user): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths(11);
        $end = CarbonImmutable::today();
        $months = [];

        foreach (CarbonPeriod::create($start, '1 month', $end) as $month) {
            $months[$month->format('Y-m')] = ['label' => $month->format('M Y'), 'revenue' => 0.0, 'expenses' => 0.0];
        }

        $monthly = fn ($query, string $column) => $query->whereBetween($column, [$start->toDateString(), $end->toDateString()])
            ->selectRaw("YEAR({$column}) as y, MONTH({$column}) as m, SUM(amount) as total")->groupByRaw("YEAR({$column}), MONTH({$column})")->get();

        $datasets = [];

        if ($user->can('payments.view')) {
            foreach ($monthly(Payment::query()->completed()->toBase(), 'payment_date') as $row) {
                $months[sprintf('%04d-%02d', $row->y, $row->m)]['revenue'] = (float) $row->total;
            }

            $datasets[] = ['label' => 'Revenue', 'key' => 'revenue'];
        }

        if ($user->can('expenses.view')) {
            foreach ($monthly(Expense::query()->recorded()->toBase(), 'expense_date') as $row) {
                $months[sprintf('%04d-%02d', $row->y, $row->m)]['expenses'] += (float) $row->total;
            }

            $withPayroll = $user->can('dashboard.view_payroll');

            if ($withPayroll) {
                foreach ($this->payrollCosts->between($start, $end) as $cost) {
                    $key = substr($cost['date'], 0, 7);

                    if (isset($months[$key])) {
                        $months[$key]['expenses'] += (float) $cost['amount'];
                    }
                }
            }

            $datasets[] = ['label' => $withPayroll ? 'Expenses incl. payroll' : 'Expenses (excl. payroll)', 'key' => 'expenses'];
        }

        return [
            'type' => 'bar',
            'labels' => array_values(array_column($months, 'label')),
            'datasets' => array_map(fn (array $dataset): array => [
                'label' => $dataset['label'],
                'data' => array_values(array_column($months, $dataset['key'])),
            ], $datasets),
            'money' => true,
        ];
    }

    /**
     * Latest payments, issued invoices and stock movements the user may see (FR-DSH-03).
     *
     * @return list<array{at: CarbonImmutable, icon: string, title: string, detail: string, amount: string|null, url: string|null}>
     */
    private function recent(User $user): array
    {
        $entries = [];

        if ($user->can('payments.view')) {
            foreach (Payment::query()->with('customer:id,name')->latest('id')->limit(5)->get() as $payment) {
                $entries[] = [
                    'at' => CarbonImmutable::parse($payment->created_at),
                    'icon' => 'cash-coin',
                    'title' => ($payment->isCompleted() ? 'Payment ' : 'Reversed payment ').$payment->number,
                    'detail' => (string) $payment->customer?->name.' · '.$payment->method->label(),
                    'amount' => $this->settings->formatMoney((string) $payment->amount),
                    'url' => route('invoices.show', $payment->invoice_id),
                ];
            }
        }

        if ($user->can('invoices.view')) {
            foreach (Invoice::query()->whereNotNull('issued_at')->with('customer:id,name')->latest('issued_at')->limit(5)->get() as $invoice) {
                $entries[] = [
                    'at' => CarbonImmutable::parse($invoice->issued_at),
                    'icon' => 'receipt',
                    'title' => 'Invoice '.$invoice->displayNumber().' issued',
                    'detail' => (string) $invoice->customer?->name.' · '.$invoice->status->label(),
                    'amount' => $this->settings->formatMoney((string) $invoice->total),
                    'url' => route('invoices.show', $invoice),
                ];
            }
        }

        if ($user->can('stock.view')) {
            foreach (StockMovement::query()->with(['item:id,code,name', 'warehouse:id,code'])->latest('id')->limit(5)->get() as $movement) {
                $entries[] = [
                    'at' => CarbonImmutable::parse($movement->moved_at),
                    'icon' => 'arrow-left-right',
                    'title' => $movement->type->label().': '.$movement->item->name,
                    'detail' => ($movement->direction === 1 ? '+' : '−').rtrim(rtrim((string) $movement->quantity, '0'), '.').' at '.$movement->warehouse->code,
                    'amount' => null,
                    'url' => route('stock.movements'),
                ];
            }
        }

        usort($entries, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return array_slice($entries, 0, 10);
    }

    /**
     * @return array{label: string, value: string, icon: string, variant: string, href: string|null, hint: string|null}
     */
    private function card(string $label, string $value, string $icon, string $variant, ?string $href, ?string $hint = null): array
    {
        return ['label' => $label, 'value' => $value, 'icon' => $icon, 'variant' => $variant, 'href' => $href, 'hint' => $hint];
    }
}
