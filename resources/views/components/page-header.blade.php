@props(['title', 'subtitle' => null, 'back' => null, 'breadcrumbs' => null])

<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div class="me-auto">
        @if ($breadcrumbs)
            <x-breadcrumb :items="$breadcrumbs" />
        @elseif ($back)
            <a href="{{ $back }}" class="small text-secondary text-decoration-none"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</a>
        @endif
        <h1 class="h4 mb-0">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-secondary mb-0 small">{{ $subtitle }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endif
</div>
