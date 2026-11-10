<x-layouts::app title="Dashboard">
    <x-page-header title="Welcome, {{ $user->name }}" :subtitle="$user->getRoleNames()->implode(', ') ?: 'No role assigned'" />

    <div class="row g-3 mb-4">
        @isset($stats['activeUsers'])
            <div class="col-sm-6 col-xl-3">
                <x-stat-card label="Active users" :value="number_format($stats['activeUsers'])" icon="people" :href="route('admin.users.index', ['status' => 'active'])" />
            </div>
        @endisset
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Unread notifications" :value="number_format($stats['unreadNotifications'])" icon="bell" variant="warning"
                :href="route('notifications.index')" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Two-factor sign-in" :value="$user->hasTwoFactorEnabled() ? 'On' : 'Off'" icon="shield-check"
                :variant="$user->hasTwoFactorEnabled() ? 'success' : 'secondary'" :href="route('account.security')" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Last sign-in" :value="$stats['lastLogin']" icon="clock-history" variant="info" />
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-grid-1x2 text-primary fs-1" aria-hidden="true"></i>
            <h2 class="h5 mt-3">Your dashboard</h2>
            <p class="text-secondary mb-0">Summaries for sales, inventory, payroll and requisitions will appear here as each module goes live.</p>
        </div>
    </div>
</x-layouts::app>
