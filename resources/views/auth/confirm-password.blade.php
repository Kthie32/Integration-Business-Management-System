<x-layouts::guest title="Confirm password">
    <h1 class="h5 mb-1">Confirm your password</h1>
    <p class="text-secondary small mb-4">This is a secure area. Please confirm your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm.store') }}" novalidate>
        @csrf

        <x-form.input name="password" label="Password" type="password" autocomplete="current-password" required autofocus />

        <button type="submit" class="btn btn-primary w-100">Confirm</button>
    </form>
</x-layouts::guest>
