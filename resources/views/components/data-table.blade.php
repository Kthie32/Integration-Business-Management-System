@props(['empty' => false, 'emptyMessage' => 'Nothing to show yet.', 'emptyIcon' => 'inbox', 'paginator' => null])

{{-- A card-wrapped responsive table. Provide the header row in the "head" slot and rows in the default slot. --}}
<div {{ $attributes->class(['card']) }}>
    @if ($empty)
        <div class="card-body text-center text-secondary py-5">
            <i class="bi bi-{{ $emptyIcon }} fs-2 d-block mb-2" aria-hidden="true"></i>
            {{ $emptyMessage }}
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">{{ $head }}</thead>
                <tbody>{{ $slot }}</tbody>
            </table>
        </div>
    @endif

    @if ($paginator && $paginator->hasPages())
        <div class="card-footer bg-white">{{ $paginator->links() }}</div>
    @endif
</div>
