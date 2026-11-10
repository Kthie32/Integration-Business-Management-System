@props(['items' => []])

{{-- $items: label => url (null for the current page). The dashboard is always the first crumb. --}}
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Home</a></li>
        @foreach ($items as $label => $url)
            @if ($url && ! $loop->last)
                <li class="breadcrumb-item"><a href="{{ $url }}" class="text-decoration-none">{{ $label }}</a></li>
            @else
                <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
            @endif
        @endforeach
    </ol>
</nav>
