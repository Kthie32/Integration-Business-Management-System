<x-layouts::app title="Edit supplier">
    <x-page-header :title="'Edit '.$supplier->name" :subtitle="$supplier->code"
        :breadcrumbs="['Suppliers' => route('suppliers.index'), $supplier->name => route('suppliers.show', $supplier), 'Edit' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('suppliers.update', $supplier) }}" novalidate>
                @csrf
                @method('PUT')
                @include('suppliers._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
