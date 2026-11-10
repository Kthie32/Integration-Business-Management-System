<x-layouts::app title="Notifications">
    <x-page-header title="Notifications" :breadcrumbs="['Notifications' => null]">
        @if (auth()->user()->unreadNotifications()->exists())
            <x-slot:actions>
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">Mark all as read</button>
                </form>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="card">
        @if ($notifications->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-bell-slash fs-2 d-block mb-2" aria-hidden="true"></i>
                You have no notifications.
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach ($notifications as $notification)
                    <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                        @csrf
                        <button type="submit" @class(['list-group-item list-group-item-action d-flex gap-3 text-start', 'unread' => $notification->read_at === null])>
                            <i class="bi bi-{{ $notification->data['icon'] ?? 'info-circle' }} text-primary fs-5" aria-hidden="true"></i>
                            <span class="flex-grow-1 min-w-0">
                                <span class="d-block fw-semibold">
                                    {{ $notification->data['title'] ?? 'Notification' }}
                                    @if ($notification->read_at === null)
                                        <span class="badge text-bg-primary ms-1">New</span>
                                    @endif
                                </span>
                                <span class="d-block text-secondary small">{{ $notification->data['message'] ?? '' }}</span>
                            </span>
                            <span class="small text-secondary text-nowrap">{{ $notification->created_at->diffForHumans() }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        @endif

        @if ($notifications->hasPages())
            <div class="card-footer bg-white">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts::app>
