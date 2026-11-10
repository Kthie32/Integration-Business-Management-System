<x-layouts::app title="Tax rates">
    <x-page-header title="Settings" subtitle="Tax rates that can be applied to items and document lines."
        :breadcrumbs="['Settings' => route('settings.index'), 'Tax rates' => null]">
        <x-slot:actions>
            <a href="{{ route('settings.taxes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New tax rate</a>
        </x-slot:actions>
    </x-page-header>

    @include('settings._nav')

    <x-data-table :empty="$taxRates->isEmpty()" empty-message="No tax rates yet." empty-icon="percent">
        <x-slot:head>
            <tr>
                <th scope="col">Name</th>
                <th scope="col">Code</th>
                <th scope="col" class="text-end">Rate</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
            </tr>
        </x-slot:head>

        @foreach ($taxRates as $taxRate)
            <tr>
                <td>
                    <span class="fw-semibold">{{ $taxRate->name }}</span>
                    @if ($taxRate->is_default)
                        <x-status-badge label="Default" color="primary" class="ms-1" />
                    @endif
                </td>
                <td><code>{{ $taxRate->code }}</code></td>
                <td class="text-end">{{ $taxRate->displayRate() }}</td>
                <td>
                    @if ($taxRate->is_active)
                        <x-status-badge label="Active" color="success" />
                    @else
                        <x-status-badge label="Inactive" color="secondary" />
                    @endif
                </td>
                <td class="text-end">
                    <a href="{{ route('settings.taxes.edit', $taxRate) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <p class="small text-secondary mt-3 mb-0">Tax rates are never deleted, because past documents refer to them. Deactivate a rate to stop it being offered.</p>
</x-layouts::app>
