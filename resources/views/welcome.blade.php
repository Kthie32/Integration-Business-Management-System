<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center min-vh-100">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4 p-md-5 text-center">
                        <i class="bi bi-buildings text-primary display-5" aria-hidden="true"></i>
                        <h1 class="h3 mt-3 mb-2">{{ config('app.name') }}</h1>
                        <p class="text-secondary mb-0">Integrated Business Management System</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
