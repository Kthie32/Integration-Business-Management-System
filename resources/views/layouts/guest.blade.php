@inject('settings', App\Services\System\SettingsService::class)
@php($organizationName = $settings->organizationName())
@php($logoPath = $settings->get('organization', 'logo_path'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Sign in' }} · {{ $organizationName }}</title>
    @fonts
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @include('layouts.partials.brand-style')
</head>
<body class="auth-page d-flex align-items-center min-vh-100">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
                <div class="text-center mb-4">
                    @if ($logoPath)
                        <img src="{{ Storage::disk('public')->url($logoPath) }}" alt="" class="auth-logo">
                    @else
                        <i class="bi bi-buildings text-primary fs-1" aria-hidden="true"></i>
                    @endif
                    <div class="fw-semibold fs-5 mt-2">{{ $organizationName }}</div>
                    <div class="text-secondary small">Integrated Business Management System</div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        @if (session('status'))
                            <div class="alert alert-success small" role="status">{{ session('status') }}</div>
                        @endif

                        {{ $slot }}
                    </div>
                </div>

                <p class="text-center text-secondary small mt-4 mb-0">&copy; {{ now()->year }} {{ $organizationName }}</p>
            </div>
        </div>
    </main>
</body>
</html>
