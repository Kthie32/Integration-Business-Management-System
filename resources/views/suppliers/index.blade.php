<x-layouts::app title="Suppliers">
    <x-page-header title="Suppliers" subtitle="Businesses you buy goods and services from.">
        @can('create', App\Models\Supplier::class)
            <x-slot:actions>
                <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New supplier</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-filter-bar :action="route('suppliers.index')" :reset-url="route('suppliers.index')">
        <div class="col-md-6">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Name, code, phone, email" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label small">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Any</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>
        </div>
    </x-filter-bar>

    <x-data-table :empty="$suppliers->isEmpty()" empty-message="No suppliers found." empty-icon="truck" :paginator="$suppliers">
        <x-slot:head>
            <tr>
                <th scope="col">Supplier</th>
                <th scope="col">Contact</th>
                <th scope="col">Terms</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($suppliers as $supplier)
            <tr>
                <td>
                    <a href="{{ route('suppliers.show', $supplier) }}" class="fw-semibold text-decoration-none">{{ $supplier->name }}</a>
                    <div class="small text-secondary"><code>{{ $supplier->code }}</code></div>
                </td>
                <td class="small">
                    {{ $supplier->contact_person }}
                    <div class="text-secondary">{{ collect([$supplier->phone, $supplier->email])->filter()->implode(' · ') }}</div>
                </td>
                <td class="small">{{ $supplier->payment_terms_days }} days</td>
                <td>
                    @if ($supplier->is_active)
                        <x-status-badge label="Active" color="success" />
                    @else
                        <x-status-badge label="Inactive" color="secondary" />
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
