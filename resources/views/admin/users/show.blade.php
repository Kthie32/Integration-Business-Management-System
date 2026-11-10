<x-layouts::app :title="$user->name">
    <x-page-header :title="$user->name" :subtitle="$user->email" :back="route('admin.users.index')">
        <x-slot:actions>
            @can('update', $user)
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
            @can('sendPasswordLink', $user)
                <form method="POST" action="{{ route('admin.users.password-link', $user) }}" data-confirm="Email {{ $user->name }} a link to set a new password?">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-envelope me-1" aria-hidden="true"></i>Send password link</button>
                </form>
            @endcan
            @if ($user->is_active)
                @can('deactivate', $user)
                    <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" data-confirm="Deactivate {{ $user->name }}? They will be signed out immediately.">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-person-slash me-1" aria-hidden="true"></i>Deactivate</button>
                    </form>
                @endcan
            @else
                @can('activate', $user)
                    <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-outline-success"><i class="bi bi-person-check me-1" aria-hidden="true"></i>Reactivate</button>
                    </form>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header fw-semibold">Account</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-secondary fw-normal">Status</dt>
                        <dd class="col-7">
                            @if ($user->is_active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                                <span class="text-secondary">since {{ $user->deactivated_at?->format('d M Y') }}</span>
                            @endif
                        </dd>
                        <dt class="col-5 text-secondary fw-normal">Phone</dt>
                        <dd class="col-7">{{ $user->phone ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Roles</dt>
                        <dd class="col-7">
                            @forelse ($user->roles as $role)
                                <span class="badge text-bg-light border">{{ $role->name }}</span>
                            @empty
                                —
                            @endforelse
                        </dd>
                        <dt class="col-5 text-secondary fw-normal">Two-factor</dt>
                        <dd class="col-7">{{ $user->hasTwoFactorEnabled() ? 'On' : 'Off' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Last sign-in</dt>
                        <dd class="col-7">{{ $user->last_login_at?->format('d M Y H:i') ?? 'Never' }}@if ($user->last_login_ip) <span class="text-secondary">from {{ $user->last_login_ip }}</span>@endif</dd>
                        <dt class="col-5 text-secondary fw-normal">Created</dt>
                        <dd class="col-7 mb-0">{{ $user->created_at?->format('d M Y') }}@if ($user->creator) <span class="text-secondary">by {{ $user->creator->name }}</span>@endif</dd>
                    </dl>
                </div>
            </div>

            @can('manageSessions', $user)
                <div class="card mt-4">
                    <div class="card-header fw-semibold d-flex align-items-center">
                        Active sessions
                        @if ($sessions->isNotEmpty())
                            <form method="POST" action="{{ route('admin.users.sessions.destroy-all', $user) }}" class="ms-auto" data-confirm="Sign {{ $user->name }} out of every device?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">End all</button>
                            </form>
                        @endif
                    </div>
                    <div class="card-body">
                        <x-session-list :sessions="$sessions" destroy-route="admin.users.sessions.destroy" :route-parameters="['user' => $user]" />
                    </div>
                </div>
            @endcan
        </div>

        @can('audit.view')
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header fw-semibold d-flex align-items-center">
                        Recent activity
                        <a href="{{ route('admin.audit-logs.index') }}" class="ms-auto small">Full audit log</a>
                    </div>
                    <ul class="list-group list-group-flush">
                        @forelse ($activity as $entry)
                            <li class="list-group-item small">
                                <a href="{{ route('admin.audit-logs.show', $entry) }}" class="fw-semibold text-decoration-none">{{ str_replace('_', ' ', $entry->event) }}</a>
                                @if ($entry->description) — {{ $entry->description }}@endif
                                <div class="text-secondary">{{ $entry->created_at->format('d M Y H:i') }} · {{ $entry->user?->name ?? 'System' }}</div>
                            </li>
                        @empty
                            <li class="list-group-item small text-secondary">No activity recorded.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endcan
    </div>
</x-layouts::app>
