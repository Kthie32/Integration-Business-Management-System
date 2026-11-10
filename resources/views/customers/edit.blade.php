<x-layouts::app title="Edit customer">
    <x-page-header :title="'Edit '.$customer->name" :subtitle="$customer->code"
        :breadcrumbs="['Customers' => route('customers.index'), $customer->name => route('customers.show', $customer), 'Edit' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.update', $customer) }}" novalidate>
                @csrf
                @method('PUT')
                @include('customers._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
