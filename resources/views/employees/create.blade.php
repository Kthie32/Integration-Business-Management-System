<x-layouts::app title="Register employee">
    <x-page-header title="Register employee" subtitle="The employee number is assigned automatically."
        :breadcrumbs="['Employees' => route('employees.index'), 'New' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('employees.store') }}" novalidate>
                @csrf
                @include('employees._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Register employee</button>
                    <a href="{{ route('employees.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
