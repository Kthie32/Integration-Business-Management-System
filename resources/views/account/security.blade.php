<x-layouts::app title="Security">
    <x-page-header title="Security" subtitle="Devices signed in to your account." />

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header fw-semibold">Signed-in devices</div>
                <div class="card-body">
                    <x-session-list :sessions="$sessions" destroy-route="account.sessions.destroy" />

                    @if ($sessions->count() > 1)
                        <hr>
                        <form method="POST" action="{{ route('account.sessions.destroy-others') }}" class="row g-2 align-items-start" novalidate>
                            @csrf
                            @method('DELETE')
                            <div class="col-sm">
                                <label for="logout_password" class="visually-hidden">Your password</label>
                                <input type="password" name="password" id="logout_password" placeholder="Your password" autocomplete="current-password"
                                    @class(['form-control', 'is-invalid' => $errors->has('password')])>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-auto">
                                <button type="submit" class="btn btn-outline-danger">Sign out other devices</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
