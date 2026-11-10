@foreach (['success' => 'check-circle', 'warning' => 'exclamation-triangle', 'error' => 'x-octagon'] as $type => $icon)
    @if (session($type))
        <div class="alert alert-{{ $type === 'error' ? 'danger' : $type }} alert-dismissible fade show d-flex align-items-start gap-2" role="{{ $type === 'success' ? 'status' : 'alert' }}">
            <i class="bi bi-{{ $icon }} mt-1" aria-hidden="true"></i>
            <div>{{ session($type) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
