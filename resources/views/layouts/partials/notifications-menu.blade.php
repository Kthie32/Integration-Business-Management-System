@php
    $unreadCount = auth()->user()->unreadNotifications()->count();
    $recentNotifications = auth()->user()->notifications()->limit(8)->get();
@endphp

<div class="dropdown">
    <button class="btn btn-link text-body position-relative px-2" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
        aria-label="Notifications{{ $unreadCount > 0 ? " ({$unreadCount} unread)" : '' }}">
        <i class="bi bi-bell fs-5" aria-hidden="true"></i>
        @if ($unreadCount > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-count" data-unread-count>
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow-sm p-0 notifications-menu">
        <div class="d-flex align-items-center px-3 py-2 border-bottom">
            <span class="fw-semibold">Notifications</span>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}" class="ms-auto">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm p-0">Mark all as read</button>
                </form>
            @endif
        </div>

        <div class="list-group list-group-flush notifications-menu-list">
            @forelse ($recentNotifications as $notification)
                <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                    @csrf
                    <button type="submit" @class(['list-group-item list-group-item-action d-flex gap-2 text-start border-0', 'unread' => $notification->read_at === null])>
                        <i class="bi bi-{{ $notification->data['icon'] ?? 'info-circle' }} text-primary mt-1" aria-hidden="true"></i>
                        <span class="min-w-0">
                            <span class="d-block small fw-semibold text-truncate">{{ $notification->data['title'] ?? 'Notification' }}</span>
                            <span class="d-block small text-secondary">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="d-block small text-secondary">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </form>
            @empty
                <div class="text-center text-secondary small py-4">You're all caught up.</div>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}" class="d-block text-center small py-2 border-top text-decoration-none">See all notifications</a>
    </div>
</div>
