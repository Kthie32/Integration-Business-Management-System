@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Dashboard">
    <x-page-header title="Welcome, {{ $user->name }}" :subtitle="$user->getRoleNames()->implode(', ') ?: 'No role assigned'">
        <x-slot:actions>
            @canany(['reports.financial', 'reports.sales', 'reports.inventory', 'reports.payroll', 'reports.requisitions'])
                <a href="{{ route('reports.index') }}" class="btn btn-outline-primary"><i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>Reports</a>
            @endcanany
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('dashboard') }}" class="d-flex flex-wrap align-items-end gap-2 mb-3" aria-label="Dashboard period">
        <div class="btn-group" role="group" aria-label="Period">
            @foreach (array_diff_key($periods, ['custom' => true]) as $key => $label)
                <a href="{{ route('dashboard', ['period' => $key]) }}" @class(['btn btn-sm', 'btn-primary' => $period['key'] === $key, 'btn-outline-primary' => $period['key'] !== $key])
                    @if ($period['key'] === $key) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </div>
        <input type="hidden" name="period" value="custom">
        <div>
            <label for="dashboard-from" class="form-label small mb-0">From</label>
            <input type="date" name="from" id="dashboard-from" class="form-control form-control-sm" value="{{ $period['from']->toDateString() }}">
        </div>
        <div>
            <label for="dashboard-to" class="form-label small mb-0">To</label>
            <input type="date" name="to" id="dashboard-to" class="form-control form-control-sm" value="{{ $period['to']->toDateString() }}">
        </div>
        <button type="submit" @class(['btn btn-sm', 'btn-primary' => $period['key'] === 'custom', 'btn-outline-secondary' => $period['key'] !== 'custom'])>Apply dates</button>
        <span class="small text-secondary ms-sm-2">{{ $settings->formatDate($period['from']) }} – {{ $settings->formatDate($period['to']) }}</span>
    </form>

    <div class="row g-3 mb-4">
        @foreach ($cards as $card)
            <div class="col-sm-6 col-xl-3">
                <x-stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']" :variant="$card['variant']" :hint="$card['hint']" :href="$card['href']" />
            </div>
        @endforeach
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Unread notifications" :value="number_format($personal['unreadNotifications'])" icon="bell" variant="warning"
                :href="route('notifications.index')" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Two-factor sign-in" :value="$user->hasTwoFactorEnabled() ? 'On' : 'Off'" icon="shield-check"
                :variant="$user->hasTwoFactorEnabled() ? 'success' : 'secondary'" :href="route('account.security')" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Last sign-in" :value="$personal['lastLogin']" icon="clock-history" variant="info" />
        </div>
    </div>

    @if ($charts !== [])
        <div class="row g-3 mb-4">
            @foreach ($charts as $chart)
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header bg-white d-flex align-items-center">
                            <h2 class="h6 mb-0 me-auto">{{ $chart['title'] }}</h2>
                            @if ($chart['url'])
                                <a href="{{ $chart['url'] }}" class="small">Report</a>
                            @endif
                        </div>
                        <div class="card-body">
                            @if (array_sum(array_map(fn ($dataset) => array_sum($dataset['data']), $chart['chart']['datasets'])) > 0)
                                <div style="position: relative; height: 260px;">
                                    <canvas data-chart="{{ json_encode($chart['chart']) }}" role="img" aria-label="{{ $chart['title'] }}"></canvas>
                                </div>
                            @else
                                <p class="text-secondary text-center small my-5">No data for this period yet.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($recent !== [])
        <div class="card">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Recent transactions</h2></div>
            <ul class="list-group list-group-flush">
                @foreach ($recent as $entry)
                    <li class="list-group-item d-flex align-items-center gap-3">
                        <i class="bi bi-{{ $entry['icon'] }} text-secondary" aria-hidden="true"></i>
                        <div class="me-auto min-w-0">
                            @if ($entry['url'])
                                <a href="{{ $entry['url'] }}" class="text-decoration-none">{{ $entry['title'] }}</a>
                            @else
                                {{ $entry['title'] }}
                            @endif
                            <div class="small text-secondary text-truncate">{{ $entry['detail'] }} · {{ $settings->formatDate($entry['at']) }}</div>
                        </div>
                        @if ($entry['amount'])
                            <span class="text-nowrap">{{ $entry['amount'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-layouts::app>
