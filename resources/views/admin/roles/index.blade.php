<x-layouts::app title="Roles & permissions">
    <x-page-header title="Roles &amp; permissions" subtitle="Each role is a bundle of permissions. Users can hold several roles.">
        @can('create', App\Models\Role::class)
            <x-slot:actions>
                <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New role</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Role</th>
                        <th scope="col" class="text-center">Users</th>
                        <th scope="col" class="text-center">Permissions</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>
                                <a href="{{ route('admin.roles.edit', $role) }}" class="fw-semibold text-decoration-none">{{ $role->name }}</a>
                                @if ($role->is_system)
                                    <span class="badge text-bg-light border ms-1">System</span>
                                @endif
                                @if ($role->description)
                                    <div class="small text-secondary">{{ $role->description }}</div>
                                @endif
                            </td>
                            <td class="text-center">{{ $role->users_count }}</td>
                            <td class="text-center">
                                @if ($role->isSuperAdministrator())
                                    All
                                @else
                                    {{ $role->permissions_count }} / {{ $permissionTotal }}
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('update', $role)
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @else
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                @endcan
                                @can('delete', $role)
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" data-confirm="Delete the role &quot;{{ $role->name }}&quot;?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
