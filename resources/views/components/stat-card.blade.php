@props(['label', 'value', 'icon' => 'bar-chart', 'variant' => 'primary', 'hint' => null, 'href' => null])

<div {{ $attributes->class(['card stat-card h-100']) }}>
    <div class="card-body d-flex align-items-start gap-3">
        <span class="stat-card-icon text-{{ $variant }} bg-{{ $variant }}-subtle" aria-hidden="true"><i class="bi bi-{{ $icon }}"></i></span>
        <div class="min-w-0">
            <div class="text-secondary small">{{ $label }}</div>
            <div class="fs-4 fw-semibold lh-sm text-truncate">{{ $value }}</div>
            @if ($hint)
                <div class="small text-secondary mt-1">{{ $hint }}</div>
            @endif
        </div>
    </div>
    @if ($href)
        <a href="{{ $href }}" class="stretched-link" aria-label="View {{ $label }}"></a>
    @endif
</div>
