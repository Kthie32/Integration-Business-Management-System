<x-layouts::app title="Customers">
    <x-page-header title="Customers" subtitle="People and companies you sell to.">
        @can('create', App\Models\Customer::class)
            <x-slot:actions>
                <a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New customer</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-filter-bar :action="route('customers.index')" :reset-url="route('customers.index')">
        <div class="col-md-5">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Name, code, phone, email" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label for="type" class="form-label small">Type</label>
            <select name="type" id="type" class="form-select">
                <option value="">All</option>
                @foreach (App\Enums\CustomerType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
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

    <x-data-table :empty="$customers->isEmpty()" empty-message="No customers found." empty-icon="person-vcard" :paginator="$customers">
        <x-slot:head>
            <tr>
                <th scope="col">Customer</th>
                <th scope="col">Contact</th>
                <th scope="col">Terms</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($customers as $customer)
            <tr>
                <td>
                    <a href="{{ route('customers.show', $customer) }}" class="fw-semibold text-decoration-none">{{ $customer->name }}</a>
                    <div class="small text-secondary"><code>{{ $customer->code }}</code> · {{ $customer->type->label() }}</div>
                </td>
                <td class="small">
                    {{ $customer->contact_person }}
                    <div class="text-secondary">{{ collect([$customer->phone, $customer->email])->filter()->implode(' · ') }}</div>
                </td>
                <td class="small">{{ $customer->payment_terms_days }} days</td>
                <td>
                    @if ($customer->is_active)
                        <x-status-badge label="Active" color="success" />
                    @else
                        <x-status-badge label="Inactive" color="secondary" />
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
