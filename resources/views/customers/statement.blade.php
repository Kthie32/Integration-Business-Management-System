@inject('settings', App\Services\System\SettingsService::class)
@php
    $customer = $statement['customer'];
    $range = ['from' => $statement['from']->toDateString(), 'to' => $statement['to']->toDateString()];
@endphp
<x-layouts::app :title="'Statement · '.$customer->name">
    <x-page-header title="Statement of account" :subtitle="$customer->name.' · '.$settings->formatDate($statement['from']).' to '.$settings->formatDate($statement['to'])"
        :breadcrumbs="['Customers' => route('customers.index'), $customer->name => route('customers.show', $customer), 'Statement' => null]">
        <x-slot:actions>
            <div class="btn-group">
                <a href="{{ route('customers.statement.pdf', [$customer, ...$range]) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF</a>
                <a href="{{ route('customers.statement.print', [$customer, ...$range]) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</a>
            </div>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('customers.statement', $customer)" :reset-url="route('customers.statement', $customer)">
        <div class="col-sm-4 col-md-3">
            <label for="from" class="form-label small">From</label>
            <input type="date" name="from" id="from" class="form-control" value="{{ $range['from'] }}">
        </div>
        <div class="col-sm-4 col-md-3">
            <label for="to" class="form-label small">To</label>
            <input type="date" name="to" id="to" class="form-control" value="{{ $range['to'] }}">
        </div>
    </x-filter-bar>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-stat-card label="Opening balance" :value="$settings->formatMoney($statement['opening'])" icon="hourglass-top" variant="secondary" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card label="Invoiced and charged" :value="$settings->formatMoney($statement['total_debit'])" icon="receipt" variant="info" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card label="Paid and credited" :value="$settings->formatMoney($statement['total_credit'])" icon="cash-coin" variant="success" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card label="Closing balance" :value="$settings->formatMoney($statement['closing'])" icon="wallet2" :variant="App\Support\Money::of($statement['closing'])->isPositive() ? 'warning' : 'success'" /></div>
    </div>

    <x-data-table>
        <x-slot:head>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Type</th>
                <th scope="col">Reference</th>
                <th scope="col">Description</th>
                <th scope="col" class="text-end">Debit</th>
                <th scope="col" class="text-end">Credit</th>
                <th scope="col" class="text-end">Balance</th>
            </tr>
        </x-slot:head>

        <tr class="table-light">
            <td class="small text-nowrap">{{ $settings->formatDate($statement['from']) }}</td>
            <td colspan="5" class="fw-semibold">Opening balance</td>
            <td class="text-end fw-semibold"><x-money :amount="$statement['opening']" /></td>
        </tr>
        @forelse ($statement['lines'] as $line)
            <tr>
                <td class="small text-nowrap">{{ $settings->formatDate($line['date']) }}</td>
                <td class="small">{{ $line['type'] }}</td>
                <td class="small">
                    @if ($line['invoice_id'] && str_starts_with($line['type'], 'Invoice'))
                        <a href="{{ route('invoices.show', $line['invoice_id']) }}" class="text-decoration-none">{{ $line['reference'] }}</a>
                    @else
                        {{ $line['reference'] }}
                    @endif
                </td>
                <td class="small text-secondary">{{ $line['description'] }}</td>
                <td class="text-end">@if ($line['debit'] !== null)<x-money :amount="$line['debit']" />@endif</td>
                <td class="text-end">@if ($line['credit'] !== null)<x-money :amount="$line['credit']" />@endif</td>
                <td class="text-end"><x-money :amount="$line['balance']" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-secondary small py-4">No transactions in this period.</td></tr>
        @endforelse
        <tr class="table-light fw-semibold">
            <td class="small text-nowrap">{{ $settings->formatDate($statement['to']) }}</td>
            <td colspan="3">Closing balance</td>
            <td class="text-end"><x-money :amount="$statement['total_debit']" /></td>
            <td class="text-end"><x-money :amount="$statement['total_credit']" /></td>
            <td class="text-end"><x-money :amount="$statement['closing']" /></td>
        </tr>
    </x-data-table>
</x-layouts::app>
