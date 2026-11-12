<x-layouts::app title="Pay components">
    <x-page-header title="Pay components" subtitle="Allowances and deductions assigned in employees' salary structures. A component in use can be deactivated but not deleted."
        :breadcrumbs="['Payroll' => route('payroll-runs.index'), 'Pay components' => null]" />

    <div class="row g-4">
        <div class="col-lg-7">
            <x-data-table :empty="$components->isEmpty()" empty-message="No pay components yet." empty-icon="list-ul">
                <x-slot:head>
                    <tr>
                        <th scope="col">Component</th>
                        <th scope="col">Type</th>
                        <th scope="col">Calculation</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </x-slot:head>

                @foreach ($components as $item)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $item->name }}</span> <code class="ms-1">{{ $item->code }}</code>
                            <div class="small text-secondary">
                                @if ($item->isAllowance())
                                    {{ $item->is_taxable ? 'Taxable' : 'Not taxable' }} · {{ $item->is_nssf_applicable ? 'NSSF applies' : 'No NSSF' }}
                                @else
                                    {{ $item->is_taxable ? 'Before PAYE' : 'After PAYE' }} · {{ $item->is_nssf_applicable ? 'before NSSF' : 'after NSSF' }}
                                @endif
                                · {{ $item->structure_components_count }} {{ Str::plural('salary', $item->structure_components_count) }}
                            </div>
                        </td>
                        <td>{{ $item->kind->label() }}</td>
                        <td class="small">{{ $item->calculation->label() }}</td>
                        <td>
                            @if ($item->is_active)
                                <x-status-badge label="Active" color="success" />
                            @else
                                <x-status-badge label="Inactive" color="secondary" />
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @can('update', $item)
                                <a href="{{ route('pay-components.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            @endcan
                            @can('delete', $item)
                                @unless ($item->isInUse())
                                    <form method="POST" action="{{ route('pay-components.destroy', $item) }}" class="d-inline" data-confirm="Delete {{ $item->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete {{ $item->name }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                    </form>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>

        @can('create', App\Models\PayComponent::class)
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header fw-semibold">New pay component</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pay-components.store') }}" novalidate>
                            @csrf
                            @include('payroll.components._form')
                            <button type="submit" class="btn btn-primary">Add component</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
</x-layouts::app>
