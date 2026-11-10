{{-- Recent audit-log entries for a record. Expects $activity (a collection of AuditLog). --}}
@inject('settings', App\Services\System\SettingsService::class)

<div class="card">
    <div class="card-header fw-semibold d-flex align-items-center">
        Recent activity
        <a href="{{ route('admin.audit-logs.index') }}" class="ms-auto small">Full audit log</a>
    </div>
    <ul class="list-group list-group-flush">
        @forelse ($activity as $entry)
            <li class="list-group-item small">
                <a href="{{ route('admin.audit-logs.show', $entry) }}" class="fw-semibold text-decoration-none">{{ ucfirst(str_replace('_', ' ', $entry->event)) }}</a>
                @if ($entry->description) — {{ $entry->description }}@endif
                <div class="text-secondary">{{ $settings->formatDate($entry->created_at) }} {{ $entry->created_at->format('H:i') }} · {{ $entry->user?->name ?? 'System' }}</div>
            </li>
        @empty
            <li class="list-group-item small text-secondary">No activity recorded.</li>
        @endforelse
    </ul>
</div>
