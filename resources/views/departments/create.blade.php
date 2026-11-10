<x-layouts::app title="New department">
    <x-page-header title="New department" :breadcrumbs="['Departments' => route('departments.index'), 'New' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('departments.store') }}" novalidate>
                @csrf
                @include('departments._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create department</button>
                    <a href="{{ route('departments.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
