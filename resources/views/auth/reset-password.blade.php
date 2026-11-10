<x-layouts::guest title="Set password">
    <h1 class="h5 mb-1">Set your password</h1>
    <p class="text-secondary small mb-4">At least 8 characters, with upper- and lower-case letters and a number.</p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.input name="email" label="Email address" type="email" :value="$request->email" autocomplete="username" required readonly />
        <x-form.input name="password" label="New password" type="password" autocomplete="new-password" required autofocus />
        <x-form.input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />

        <button type="submit" class="btn btn-primary w-100">Save password</button>
    </form>
</x-layouts::guest>
