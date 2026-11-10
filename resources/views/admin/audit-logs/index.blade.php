<x-layouts::app title="Audit log">
    <x-page-header title="Audit log" subtitle="A permanent record of sign-ins and changes. Entries cannot be edited or deleted." />

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-2">
                <div class="col-md-3">
                    <label for="event" class="visually-hidden">Event</label>
                    <select name="event" id="event" class="form-select">
                        <option value="">All events</option>
                        @foreach ($events as $event)
                            <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ str_replace('_', ' ', $event) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="user" class="visually-hidden">User</label>
                    <select name="user" id="user" class="form-select">
                        <option value="">All users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) ($filters['user'] ?? 0) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="from" class="visually-hidden">From</label>
                    <input type="date" name="from" id="from" class="form-control @error('from') is-invalid @enderror" value="{{ $filters['from'] ?? '' }}" title="From">
                </div>
                <div class="col-6 col-md-2">
                    <label for="to" class="visually-hidden">To</label>
                    <input type="date" name="to" id="to" class="form-control @error('to') is-invalid @enderror" value="{{ $filters['to'] ?? '' }}" title="To">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1">Filter</button>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-secondary" title="Clear filters"><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Clear filters</span></a>
                </div>
            </form>
            @if ($errors->any())
                <div class="text-danger small mt-2">{{ $errors->first() }}</div>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">User</th>
                        <th scope="col">Event</th>
                        <th scope="col">Record</th>
                        <th scope="col">Details</th>
                        <th scope="col">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap"><a href="{{ route('admin.audit-logs.show', $log) }}" class="text-decoration-none">{{ $log->created_at->format('d M Y H:i:s') }}</a></td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td><span class="badge text-bg-light border">{{ str_replace('_', ' ', $log->event) }}</span></td>
                            <td class="text-secondary">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                            <td>{{ $log->description ?? '' }}</td>
                            <td class="text-secondary">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">No entries match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="card-body border-top">{{ $logs->links() }}</div>
        @endif
    </div>
</x-layouts::app>
