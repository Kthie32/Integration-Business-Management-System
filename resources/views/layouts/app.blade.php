@inject('settings', App\Services\System\SettingsService::class)
@php($organizationName = $settings->organizationName())
@php($logoPath = $settings->get('organization', 'logo_path'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Dashboard' }} · {{ $organizationName }}</title>
    @fonts
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @include('layouts.partials.brand-style')
    <script>
        try {
            if (localStorage.getItem('ibms.sidebar') === 'collapsed') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (error) {}
    </script>
</head>
<body>
    <a href="#main-content" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-primary z-3">Skip to content</a>

    <div class="app-shell">
        <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
            <div class="app-sidebar-brand">
                @if ($logoPath)
                    <img src="{{ Storage::disk('public')->url($logoPath) }}" alt="" class="app-sidebar-logo">
                @else
                    <i class="bi bi-buildings" aria-hidden="true"></i>
                @endif
                <span id="appSidebarLabel" class="app-sidebar-label text-truncate">{{ $organizationName }}</span>
                <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close menu"></button>
            </div>

            <nav class="app-sidebar-nav" aria-label="Main">
                <x-nav-link route="dashboard" icon="speedometer2">Dashboard</x-nav-link>

                @can('customers.view')
                    <div class="app-sidebar-heading">Sales</div>
                    <x-nav-link route="customers.index" active="customers.*" icon="person-vcard">Customers</x-nav-link>
                @endcan

                @can('suppliers.view')
                    <div class="app-sidebar-heading">Procurement</div>
                    <x-nav-link route="suppliers.index" active="suppliers.*" icon="truck">Suppliers</x-nav-link>
                @endcan

                @canany(['items.view', 'stock.view', 'stock.receive', 'stock.adjust', 'stock.adjust_approve', 'warehouses.manage'])
                    <div class="app-sidebar-heading">Inventory</div>
                    @can('items.view')
                        <x-nav-link route="items.index" active="items.*" icon="box-seam">Items</x-nav-link>
                    @endcan
                    @can('stock.view')
                        <x-nav-link route="stock.levels" active="stock.levels" icon="stack">Stock levels</x-nav-link>
                        <x-nav-link route="stock.movements" active="stock.movements" icon="arrow-left-right">Stock movements</x-nav-link>
                    @endcan
                    @canany(['stock.view', 'stock.receive'])
                        <x-nav-link route="goods-receipts.index" active="goods-receipts.*" icon="box-arrow-in-down">Goods received</x-nav-link>
                    @endcanany
                    @canany(['stock.adjust', 'stock.adjust_approve', 'stock.view'])
                        <x-nav-link route="stock-adjustments.index" active="stock-adjustments.*" icon="sliders">Adjustments</x-nav-link>
                    @endcanany
                    @can('items.view')
                        <x-nav-link route="item-categories.index" active="item-categories.*" icon="tags">Categories</x-nav-link>
                    @endcan
                    @canany(['warehouses.manage', 'stock.view'])
                        <x-nav-link route="warehouses.index" active="warehouses.*" icon="building">Warehouses</x-nav-link>
                    @endcanany
                @endcanany

                @canany(['viewAny'], App\Models\Employee::class)
                    <div class="app-sidebar-heading">People</div>
                    <x-nav-link route="employees.index" active="employees.*" icon="person-badge">Employees</x-nav-link>
                    <x-nav-link route="departments.index" active="departments.*" icon="diagram-3">Departments</x-nav-link>
                @elsecanany(['viewAny'], App\Models\Department::class)
                    <div class="app-sidebar-heading">People</div>
                    <x-nav-link route="departments.index" active="departments.*" icon="diagram-3">Departments</x-nav-link>
                @endcanany

                @canany(['users.view', 'roles.manage', 'audit.view', 'settings.organization', 'settings.finance', 'settings.system'])
                    <div class="app-sidebar-heading">Administration</div>
                    @can('users.view')
                        <x-nav-link route="admin.users.index" active="admin.users.*" icon="people">Users</x-nav-link>
                    @endcan
                    @can('roles.manage')
                        <x-nav-link route="admin.roles.index" active="admin.roles.*" icon="shield-lock">Roles &amp; permissions</x-nav-link>
                    @endcan
                    @canany(['settings.organization', 'settings.finance', 'settings.system'])
                        <x-nav-link route="settings.index" active="settings.*" icon="gear">Settings</x-nav-link>
                    @endcanany
                    @can('audit.view')
                        <x-nav-link route="admin.audit-logs.index" active="admin.audit-logs.*" icon="journal-text">Audit log</x-nav-link>
                    @endcan
                @endcanany
            </nav>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <button class="btn btn-link text-body d-lg-none px-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open menu">
                    <i class="bi bi-list fs-4" aria-hidden="true"></i>
                </button>
                <button class="btn btn-link text-body d-none d-lg-inline-flex px-0 me-2" type="button" data-sidebar-toggle aria-controls="appSidebar" aria-label="Collapse or expand the menu">
                    <i class="bi bi-layout-sidebar fs-5" aria-hidden="true"></i>
                </button>

                <div class="ms-auto d-flex align-items-center gap-1">
                    @include('layouts.partials.notifications-menu')

                    <div class="dropdown">
                        <button class="btn btn-link text-body text-decoration-none d-flex align-items-center gap-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar">{{ auth()->user()->initials() }}</span>
                            <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><h6 class="dropdown-header">{{ auth()->user()->email }}</h6></li>
                            <li><a class="dropdown-item" href="{{ route('account.profile') }}"><i class="bi bi-person me-2" aria-hidden="true"></i>My profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('account.security') }}"><i class="bi bi-shield-check me-2" aria-hidden="true"></i>Security</a></li>
                            <li><a class="dropdown-item" href="{{ route('notifications.index') }}"><i class="bi bi-bell me-2" aria-hidden="true"></i>Notifications</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="app-content" id="main-content">
                <x-flash />

                {{ $slot }}
            </main>
        </div>
    </div>

    <x-confirm-modal />
</body>
</html>
