<x-layouts::app title="New customer">
    <x-page-header title="New customer" :breadcrumbs="['Customers' => route('customers.index'), 'New' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.store') }}" novalidate>
                @csrf
                @include('customers._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create customer</button>
                    <a href="{{ route('customers.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
