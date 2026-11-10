@props(['action', 'resetUrl' => null])

{{-- A GET filter form for list pages. Put the filter fields in the slot, each in a column. --}}
<form method="GET" action="{{ $action }}" {{ $attributes->class(['card card-body mb-3']) }} role="search">
    <div class="row g-2 align-items-end">
        {{ $slot }}
        <div class="col-auto d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filter</button>
            @if ($resetUrl)
                <a href="{{ $resetUrl }}" class="btn btn-link text-secondary">Reset</a>
            @endif
        </div>
    </div>
</form>
