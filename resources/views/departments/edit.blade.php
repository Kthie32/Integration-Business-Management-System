<x-layouts::app title="Edit department">
    <x-page-header :title="'Edit '.$department->name" :breadcrumbs="['Departments' => route('departments.index'), $department->name => null]">
        @can('delete', $department)
            <x-slot:actions>
                <form method="POST" action="{{ route('departments.destroy', $department) }}" data-confirm="Delete the {{ $department->name }} department?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete</button>
                </form>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('departments.update', $department) }}" novalidate>
                @csrf
                @method('PUT')
                @include('departments._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('departments.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
