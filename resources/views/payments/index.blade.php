@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Payments">
    <x-page-header title="Payments" subtitle="Money received from customers against issued invoices."
        :breadcrumbs="['Payments' => null]" />

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4">
            <x-stat-card label="Collected (matching filters)" :value="$settings->formatMoney($collected)" icon="cash-coin" variant="success"
                hint="Completed payments only; reversed payments are excluded." />
        </div>
    </div>

    <x-filter-bar :action="route('payments.index')" :reset-url="route('payments.index')">
        <div class="col-md-3">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Number, reference, payer, invoice" value="{{ $filters['search'] ?? '' }}">
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
            <label for="method" class="form-label small">Method</label>
            <select name="method" id="method" class="form-select">
                <option value="">Any</option>
                @foreach ($methods as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['method'] ?? '') === $value)>{{ $label }}</option>
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
        <div class="col-md-1">
            <label for="from" class="form-label small">From</label>
            <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="col-md-1">
            <label for="to" class="form-label small">To</label>
            <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
        </div>
    </x-filter-bar>

    <x-data-table :empty="$payments->isEmpty()" empty-message="No payments found." empty-icon="cash-coin" :paginator="$payments">
        <x-slot:head>
            <tr>
                <th scope="col">Payment</th>
                <th scope="col">Receipt</th>
                <th scope="col">Date</th>
                <th scope="col">Customer</th>
                <th scope="col">Invoice</th>
                <th scope="col">Method</th>
                <th scope="col">Reference</th>
                <th scope="col" class="text-end">Amount</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($payments as $payment)
            <tr>
                <td class="fw-semibold">{{ $payment->number }}</td>
                <td class="small">
                    @if ($payment->receipt)
                        <a href="{{ route('receipts.show', $payment->receipt) }}" class="text-decoration-none">{{ $payment->receipt->number }}</a>
                    @else
                        —
                    @endif
                </td>
                <td class="small text-nowrap">{{ $settings->formatDate($payment->payment_date) }}</td>
                <td class="small">{{ $payment->customer->name }}</td>
                <td class="small">
                    @can('view', $payment->invoice)
                        <a href="{{ route('invoices.show', $payment->invoice) }}" class="text-decoration-none">{{ $payment->invoice->displayNumber() }}</a>
                    @else
                        {{ $payment->invoice->displayNumber() }}
                    @endcan
                </td>
                <td class="small">{{ $payment->method->label() }}</td>
                <td class="small">{{ $payment->reference ?? '—' }}</td>
                <td @class(['text-end', 'text-decoration-line-through text-secondary' => ! $payment->isCompleted()])><x-money :amount="(string) $payment->amount" /></td>
                <td><x-status-badge :status="$payment->status" /></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
