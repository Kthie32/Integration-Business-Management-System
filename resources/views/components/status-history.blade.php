@props(['histories', 'labels' => []])

{{-- A timeline of status changes. $labels optionally maps stored status values to display labels. --}}
@inject('settings', App\Services\System\SettingsService::class)

<div {{ $attributes->class(['card']) }}>
    <div class="card-header fw-semibold">Status history</div>
    <ul class="list-group list-group-flush">
        @forelse ($histories as $history)
            <li class="list-group-item small">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @if ($history->from_status)
                        <span class="text-secondary">{{ $labels[$history->from_status] ?? $history->from_status }}</span>
                        <i class="bi bi-arrow-right text-secondary" aria-hidden="true"></i>
                    @endif
                    <span class="fw-semibold">{{ $labels[$history->to_status] ?? $history->to_status }}</span>
                    <span class="ms-auto text-secondary">{{ $settings->formatDate($history->created_at) }} {{ $history->created_at->format('H:i') }}</span>
                </div>
                <div class="text-secondary">by {{ $history->user?->name ?? 'System' }}</div>
                @if ($history->comment)
                    <div class="mt-1">{{ $history->comment }}</div>
                @endif
            </li>
        @empty
            <li class="list-group-item small text-secondary">No status changes yet.</li>
        @endforelse
    </ul>
</div>
