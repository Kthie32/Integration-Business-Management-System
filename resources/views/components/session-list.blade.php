@props(['sessions', 'destroyRoute', 'routeParameters' => []])

@if ($sessions->isEmpty())
    <p class="text-secondary small mb-0">No active sessions.</p>
@else
    <ul class="list-group list-group-flush">
        @foreach ($sessions as $session)
            <li class="list-group-item px-0 d-flex align-items-center gap-3">
                <i class="bi bi-{{ str_contains(strtolower($session['user_agent']), 'mobile') ? 'phone' : 'laptop' }} fs-4 text-secondary" aria-hidden="true"></i>
                <div class="me-auto small">
                    <div class="text-truncate" style="max-width: 32rem;">{{ $session['user_agent'] ?: 'Unknown device' }}</div>
                    <div class="text-secondary">
                        {{ $session['ip_address'] ?? 'Unknown IP' }} ·
                        @if ($session['is_current'])
                            <span class="text-success fw-semibold">This device</span>
                        @else
                            last active {{ $session['last_active_at']->diffForHumans() }}
                        @endif
                    </div>
                </div>
                @unless ($session['is_current'])
                    <form method="POST" action="{{ route($destroyRoute, [...$routeParameters, 'session' => $session['key']]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">End</button>
                    </form>
                @endunless
            </li>
        @endforeach
    </ul>
@endif
