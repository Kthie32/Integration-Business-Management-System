<x-layouts::guest title="Two-factor authentication">
    <h1 class="h5 mb-1">Two-factor authentication</h1>

    <div id="totp-panel" @class(['d-none' => $errors->has('recovery_code')])>
        <p class="text-secondary small mb-4">Enter the 6-digit code from your authenticator app.</p>
        <form method="POST" action="{{ route('two-factor.login.store') }}" novalidate>
            @csrf
            <x-form.input name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus />
            <button type="submit" class="btn btn-primary w-100">Verify</button>
        </form>
        <button type="button" class="btn btn-link btn-sm w-100 mt-3" data-toggle-panels>Use a recovery code instead</button>
    </div>

    <div id="recovery-panel" @class(['d-none' => ! $errors->has('recovery_code')])>
        <p class="text-secondary small mb-4">Enter one of the recovery codes you saved when you set up two-factor authentication. Each code works once.</p>
        <form method="POST" action="{{ route('two-factor.login.store') }}" novalidate>
            @csrf
            <x-form.input name="recovery_code" label="Recovery code" autocomplete="off" />
            <button type="submit" class="btn btn-primary w-100">Verify</button>
        </form>
        <button type="button" class="btn btn-link btn-sm w-100 mt-3" data-toggle-panels>Use an authentication code instead</button>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-panels]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('totp-panel').classList.toggle('d-none');
                document.getElementById('recovery-panel').classList.toggle('d-none');
            });
        });
    </script>
</x-layouts::guest>
