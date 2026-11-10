<x-layouts::app title="Users">
    <x-page-header title="Users" subtitle="Accounts that can sign in to the system.">
        @can('create', App\Models\User::class)
            <x-slot:actions>
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>New user</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2">
                <div class="col-md-5">
                    <label for="search" class="visually-hidden">Search</label>
                    <input type="search" name="search" id="search" class="form-control" placeholder="Name, email or phone" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label for="role" class="visually-hidden">Role</label>
                    <select name="role" id="role" class="form-select">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((int) ($filters['role'] ?? 0) === $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="visually-hidden">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Any status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1">Filter</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary" title="Clear filters"><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Clear filters</span></a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Roles</th>
                        <th scope="col">Status</th>
                        <th scope="col">Last sign-in</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <a href="{{ route('admin.users.show', $user) }}" class="fw-semibold text-decoration-none">{{ $user->name }}</a>
                                <div class="small text-secondary">{{ $user->email }}</div>
                            </td>
                            <td>
                                @foreach ($user->roles as $role)
                                    <span class="badge text-bg-light border">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="small text-secondary">{{ $user->last_login_at?->format('d M Y H:i') ?? 'Never' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-4">No users match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="card-body border-top">{{ $users->links() }}</div>
        @endif
    </div>
</x-layouts::app>
