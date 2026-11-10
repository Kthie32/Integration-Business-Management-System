<x-layouts::app title="Audit entry">
    <x-page-header :title="'Audit entry #'.$log->id" :subtitle="str_replace('_', ' ', $log->event)" :back="route('admin.audit-logs.index')" />

    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-sm-3 text-secondary fw-normal">When</dt>
                <dd class="col-sm-9">{{ $log->created_at->format('d M Y H:i:s') }}</dd>
                <dt class="col-sm-3 text-secondary fw-normal">User</dt>
                <dd class="col-sm-9">{{ $log->user?->name ?? 'System' }}</dd>
                <dt class="col-sm-3 text-secondary fw-normal">Record</dt>
                <dd class="col-sm-9">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</dd>
                <dt class="col-sm-3 text-secondary fw-normal">Description</dt>
                <dd class="col-sm-9">{{ $log->description ?? '—' }}</dd>
                <dt class="col-sm-3 text-secondary fw-normal">IP address</dt>
                <dd class="col-sm-9">{{ $log->ip_address ?? '—' }}</dd>
                <dt class="col-sm-3 text-secondary fw-normal">Browser</dt>
                <dd class="col-sm-9 text-break">{{ $log->user_agent ?? '—' }}</dd>
                <dt class="col-sm-3 text-secondary fw-normal">URL</dt>
                <dd class="col-sm-9 text-break mb-0">{{ $log->url ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    @if ($log->old_values || $log->new_values)
        <div class="row g-4">
            @foreach (['Before' => $log->old_values, 'After' => $log->new_values] as $heading => $values)
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ $heading }}</div>
                        <div class="card-body">
                            @if ($values)
                                <dl class="row mb-0 small">
                                    @foreach ($values as $field => $value)
                                        <dt class="col-sm-4 text-secondary fw-normal text-break">{{ $field }}</dt>
                                        <dd class="col-sm-8 text-break"><code>{{ is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value) }}</code></dd>
                                    @endforeach
                                </dl>
                            @else
                                <p class="text-secondary small mb-0">—</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts::app>
