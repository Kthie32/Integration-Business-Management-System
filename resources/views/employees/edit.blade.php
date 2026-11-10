<x-layouts::app title="Edit employee">
    <x-page-header :title="'Edit '.$employee->fullName()" :subtitle="$employee->employee_number"
        :breadcrumbs="['Employees' => route('employees.index'), $employee->fullName() => route('employees.show', $employee), 'Edit' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('employees.update', $employee) }}" novalidate>
                @csrf
                @method('PUT')
                @include('employees._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('employees.show', $employee) }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
