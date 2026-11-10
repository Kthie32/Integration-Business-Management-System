<x-layouts::app title="Employees">
    <x-page-header title="Employees" subtitle="Staff records, departments and employment status.">
        @can('create', App\Models\Employee::class)
            <x-slot:actions>
                <a href="{{ route('employees.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Register employee</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-filter-bar :action="route('employees.index')" :reset-url="route('employees.index')">
        <div class="col-md-4">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Name, number, job title, phone" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <label for="department" class="form-label small">Department</label>
            <select name="department" id="department" class="form-select">
                <option value="">All</option>
                @foreach ($departments as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['department'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label small">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Any</option>
                @foreach (App\Enums\EmployeeStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="type" class="form-label small">Type</label>
            <select name="type" id="type" class="form-select">
                <option value="">Any</option>
                @foreach (App\Enums\EmploymentType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
    </x-filter-bar>

    <x-data-table :empty="$employees->isEmpty()" empty-message="No employees found." empty-icon="person-badge" :paginator="$employees">
        <x-slot:head>
            <tr>
                <th scope="col">Employee</th>
                <th scope="col">Department</th>
                <th scope="col">Job title</th>
                <th scope="col">Type</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($employees as $employee)
            <tr>
                <td>
                    <a href="{{ route('employees.show', $employee) }}" class="fw-semibold text-decoration-none">{{ $employee->fullName() }}</a>
                    <div class="small text-secondary"><code>{{ $employee->employee_number }}</code></div>
                </td>
                <td class="small">{{ $employee->department?->name ?? '—' }}</td>
                <td class="small">{{ $employee->job_title }}</td>
                <td class="small">{{ $employee->employment_type->label() }}</td>
                <td><x-status-badge :status="$employee->status" /></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
