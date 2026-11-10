<x-layouts::guest title="Sign in">
    <h1 class="h5 mb-1">Sign in</h1>
    <p class="text-secondary small mb-4">Use the email address your administrator registered for you.</p>

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email address" type="email" autocomplete="username" required autofocus />
        <x-form.input name="password" label="Password" type="password" autocomplete="current-password" required />

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                <label class="form-check-label small" for="remember">Keep me signed in</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>
</x-layouts::guest>
