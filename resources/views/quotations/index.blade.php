@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Quotations">
    <x-page-header title="Quotations" subtitle="Price offers to customers, from draft to acceptance.">
        @can('create', App\Models\Quotation::class)
            <x-slot:actions>
                <a href="{{ route('quotations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New quotation</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-filter-bar :action="route('quotations.index')" :reset-url="route('quotations.index')">
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
            $active = ($filters['sort'] ?? 'quote_date') === $column;
            $direction = $active && ($filters['direction'] ?? 'desc') === 'desc' ? 'asc' : 'desc';
            $url = route('quotations.index', [...$filters, 'sort' => $column, 'direction' => $direction]);
            $icon = $active ? (($filters['direction'] ?? 'desc') === 'desc' ? ' <i class="bi bi-caret-down-fill small" aria-hidden="true"></i>' : ' <i class="bi bi-caret-up-fill small" aria-hidden="true"></i>') : '';

            return '<a href="'.e($url).'" class="text-reset text-decoration-none">'.e($label).$icon.'</a>';
        };
    @endphp

    <x-data-table :empty="$quotations->isEmpty()" empty-message="No quotations found." empty-icon="file-earmark-text" :paginator="$quotations">
        <x-slot:head>
            <tr>
                <th scope="col">{!! $sortLink('number', 'Quotation') !!}</th>
                <th scope="col">{!! $sortLink('quote_date', 'Date') !!}</th>
                <th scope="col">Customer</th>
                <th scope="col">{!! $sortLink('valid_until', 'Valid until') !!}</th>
                <th scope="col" class="text-end">{!! $sortLink('total', 'Total') !!}</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($quotations as $quotation)
            <tr>
                <td>
                    <a href="{{ route('quotations.show', $quotation) }}" class="fw-semibold text-decoration-none">{{ $quotation->displayNumber() }}</a>
                    @if ($quotation->isConverted())
                        <div class="small text-success"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i> Invoiced</div>
                    @endif
                </td>
                <td class="small text-nowrap">{{ $settings->formatDate($quotation->quote_date) }}</td>
                <td class="small">{{ $quotation->customer->name }}</td>
                <td class="small text-nowrap">{{ $settings->formatDate($quotation->valid_until) }}</td>
                <td class="text-end"><x-money :amount="(string) $quotation->total" /></td>
                <td><x-status-badge :status="$quotation->status" /></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
