@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Requisitions">
    <x-page-header title="Requisitions" subtitle="Requests for goods and services, their approval and fulfilment."
        :breadcrumbs="['Requisitions' => null]">
        <x-slot:actions>
            <a href="{{ route('approvals.inbox') }}" class="btn btn-outline-secondary"><i class="bi bi-inbox me-1" aria-hidden="true"></i>My approvals</a>
            @can('create', App\Models\Requisition::class)
                <a href="{{ route('requisitions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New requisition</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('requisitions.index')" :reset-url="route('requisitions.index')">
        <div class="col-md-4">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Number, purpose or item" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <label for="department" class="form-label small">Department</label>
            <select name="department" id="department" class="form-select">
                <option value="">Any</option>
                @foreach ($departments as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['department'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label small">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Any</option>
                <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Awaiting approval</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="checkbox" name="mine" value="1" id="mine" class="form-check-input" @checked($filters['mine'] ?? false)>
                <label for="mine" class="form-check-label small">Only mine</label>
            </div>
        </div>
    </x-filter-bar>

    <x-data-table :empty="$requisitions->isEmpty()" empty-message="No requisitions found." empty-icon="clipboard-check" :paginator="$requisitions">
        <x-slot:head>
            <tr>
                <th scope="col">Requisition</th>
                <th scope="col">Department</th>
                <th scope="col">Purpose</th>
                <th scope="col">Requested by</th>
                <th scope="col">Needed by</th>
                <th scope="col" class="text-end">Estimate</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($requisitions as $requisition)
            <tr>
                <td class="fw-semibold text-nowrap">
                    <a href="{{ route('requisitions.show', $requisition) }}" class="text-decoration-none">{{ $requisition->displayNumber() }}</a>
                    @if ($requisition->priority !== App\Enums\RequisitionPriority::Normal)
                        <div><x-status-badge :label="$requisition->priority->label()" :color="$requisition->priority->color()" /></div>
                    @endif
                </td>
                <td class="small">{{ $requisition->department?->name }}</td>
                <td class="small">{{ Str::limit($requisition->purpose, 70) }}<div class="text-secondary">{{ $requisition->items_count }} {{ Str::plural('line', $requisition->items_count) }}</div></td>
                <td class="small">{{ $requisition->requester?->name }}</td>
                <td class="small text-nowrap">{{ $requisition->required_by_date ? $settings->formatDate($requisition->required_by_date) : '—' }}</td>
                <td class="text-end"><x-money :amount="(string) $requisition->estimated_total" /></td>
                <td><x-status-badge :status="$requisition->status" /></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
