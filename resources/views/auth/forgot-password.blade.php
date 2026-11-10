<x-layouts::guest title="Forgot password">
    <h1 class="h5 mb-1">Forgot your password?</h1>
    <p class="text-secondary small mb-4">Enter your email address and we will send you a link to choose a new password.</p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email address" type="email" autocomplete="username" required autofocus />

        <button type="submit" class="btn btn-primary w-100">Email reset link</button>
    </form>

    <p class="text-center small mt-4 mb-0"><a href="{{ route('login') }}">Back to sign in</a></p>
</x-layouts::guest>
