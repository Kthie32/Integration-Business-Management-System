<x-layouts::app title="Departments">
    <x-page-header title="Departments" subtitle="Organizational units, their heads and current staff.">
        @can('create', App\Models\Department::class)
            <x-slot:actions>
                <a href="{{ route('departments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New department</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table :empty="$departments->isEmpty()" empty-message="No departments yet." empty-icon="diagram-3">
        <x-slot:head>
            <tr>
                <th scope="col">Department</th>
                <th scope="col">Part of</th>
                <th scope="col">Head</th>
                <th scope="col" class="text-end">Employees</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
            </tr>
        </x-slot:head>

        @foreach ($departments as $department)
            <tr>
                <td>
                    <span class="fw-semibold">{{ $department->name }}</span>
                    <div class="small text-secondary"><code>{{ $department->code }}</code></div>
                </td>
                <td class="small">{{ $department->parent?->name ?? '—' }}</td>
                <td class="small">{{ $department->head?->fullName() ?? '—' }}</td>
                <td class="text-end">
                    @can('viewAny', App\Models\Employee::class)
                        <a href="{{ route('employees.index', ['department' => $department->id]) }}">{{ $department->employees_count }}</a>
                    @else
                        {{ $department->employees_count }}
                    @endcan
                </td>
                <td>
                    @if ($department->is_active)
                        <x-status-badge label="Active" color="success" />
                    @else
                        <x-status-badge label="Inactive" color="secondary" />
                    @endif
                </td>
                <td class="text-end">
                    @can('update', $department)
                        <a href="{{ route('departments.edit', $department) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
