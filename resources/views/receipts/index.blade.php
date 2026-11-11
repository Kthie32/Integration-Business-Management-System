@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Receipts">
    <x-page-header title="Receipts" subtitle="One receipt is issued automatically for every payment recorded. Reversing the payment voids its receipt."
        :breadcrumbs="['Receipts' => null]" />

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4">
            <x-stat-card label="Valid receipts (matching filters)" :value="$settings->formatMoney($total)" icon="receipt-cutoff" variant="success"
                hint="Void receipts are excluded." />
        </div>
    </div>

    <x-filter-bar :action="route('receipts.index')" :reset-url="route('receipts.index')">
        <div class="col-md-4">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Receipt, payment, reference, invoice, customer" value="{{ $filters['search'] ?? '' }}">
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
        <div class="col-md-1">
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

    <x-data-table :empty="$receipts->isEmpty()" empty-message="No receipts found." empty-icon="receipt-cutoff" :paginator="$receipts">
        <x-slot:head>
            <tr>
                <th scope="col">Receipt</th>
                <th scope="col">Date</th>
                <th scope="col">Customer</th>
                <th scope="col">Invoice</th>
                <th scope="col">Payment</th>
                <th scope="col" class="text-end">Amount</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
        </x-slot:head>

        @foreach ($receipts as $receipt)
            <tr>
                <td class="fw-semibold"><a href="{{ route('receipts.show', $receipt) }}" class="text-decoration-none">{{ $receipt->number }}</a></td>
                <td class="small text-nowrap">{{ $settings->formatDate($receipt->receipt_date) }}</td>
                <td class="small">{{ $receipt->customer->name }}</td>
                <td class="small">
                    @can('view', $receipt->invoice)
                        <a href="{{ route('invoices.show', $receipt->invoice) }}" class="text-decoration-none">{{ $receipt->invoice->displayNumber() }}</a>
                    @else
                        {{ $receipt->invoice->displayNumber() }}
                    @endcan
                </td>
                <td class="small">{{ $receipt->payment->number }}<div class="text-secondary">{{ $receipt->payment->method->label() }}</div></td>
                <td @class(['text-end', 'text-decoration-line-through text-secondary' => ! $receipt->isValid()])><x-money :amount="(string) $receipt->amount" /></td>
                <td><x-status-badge :status="$receipt->status" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('receipts.print', $receipt) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" aria-label="Print {{ $receipt->number }}"><i class="bi bi-printer" aria-hidden="true"></i></a>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
