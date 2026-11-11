@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Invoices">
    <x-page-header title="Invoices" subtitle="Bills to customers, from draft to payment.">
        <x-slot:actions>
            @can('viewAny', App\Models\Payment::class)
                <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-cash-coin me-1" aria-hidden="true"></i>Payments</a>
            @endcan
            @can('create', App\Models\Invoice::class)
                <a href="{{ route('invoices.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New invoice</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4">
            <x-stat-card label="Outstanding receivables" :value="$settings->formatMoney($summary['outstanding'])" icon="wallet2" :href="route('invoices.index', ['status' => 'issued'])" />
        </div>
        <div class="col-sm-6 col-xl-4">
            <x-stat-card label="Overdue ({{ number_format($summary['overdueCount']) }})" :value="$settings->formatMoney($summary['overdue'])" icon="exclamation-triangle"
                :variant="$summary['overdueCount'] > 0 ? 'danger' : 'secondary'" :href="route('invoices.index', ['status' => 'overdue'])" />
        </div>
    </div>

    <x-filter-bar :action="route('invoices.index')" :reset-url="route('invoices.index')">
        <div class="col-md-3">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Number or customer" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <label for="customer" class="form-label small">Customer</label>
            <select name="customer" id="customer" class="form-select">
                <option value="">Any</option>
                @foreach ($customers as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['customer'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label small">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Any</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="from" class="form-label small">From</label>
            <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label for="to" class="form-label small">To</label>
            <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
        </div>
    </x-filter-bar>

    @php
        $sortLink = function (string $column, string $label) use ($filters): string {
            $active = ($filters['sort'] ?? 'invoice_date') === $column;
            $direction = $active && ($filters['direction'] ?? 'desc') === 'desc' ? 'asc' : 'desc';
            $url = route('invoices.index', [...$filters, 'sort' => $column, 'direction' => $direction]);
            $icon = $active ? (($filters['direction'] ?? 'desc') === 'desc' ? ' <i class="bi bi-caret-down-fill small" aria-hidden="true"></i>' : ' <i class="bi bi-caret-up-fill small" aria-hidden="true"></i>') : '';

            return '<a href="'.e($url).'" class="text-reset text-decoration-none">'.e($label).$icon.'</a>';
        };
    @endphp

    <x-data-table :empty="$invoices->isEmpty()" empty-message="No invoices found." empty-icon="receipt" :paginator="$invoices">
        <x-slot:head>
            <tr>
                <th scope="col">{!! $sortLink('number', 'Invoice') !!}</th>
                <th scope="col">{!! $sortLink('invoice_date', 'Date') !!}</th>
                <th scope="col">Customer</th>
                <th scope="col">{!! $sortLink('due_date', 'Due') !!}</th>
                <th scope="col" class="text-end">{!! $sortLink('total', 'Total') !!}</th>
                <th scope="col" class="text-end">{!! $sortLink('balance_due', 'Balance') !!}</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($invoices as $invoice)
            <tr>
                <td><a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold text-decoration-none">{{ $invoice->displayNumber() }}</a></td>
                <td class="small text-nowrap">{{ $settings->formatDate($invoice->invoice_date) }}</td>
                <td class="small">{{ $invoice->customer->name }}</td>
                <td @class(['small text-nowrap', 'text-danger fw-semibold' => $invoice->isOverdue()])>{{ $settings->formatDate($invoice->due_date) }}</td>
                <td class="text-end"><x-money :amount="(string) $invoice->total" /></td>
                <td class="text-end"><x-money :amount="(string) $invoice->balance_due" /></td>
                <td>
                    <x-status-badge :status="$invoice->status" />
                    @if ($invoice->isOverdue())
                        <x-status-badge label="Overdue" color="danger" />
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
