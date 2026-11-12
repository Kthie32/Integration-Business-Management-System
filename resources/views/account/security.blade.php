<x-layouts::app title="Security">
    <x-page-header title="Security" subtitle="Two-factor authentication and signed-in devices." />

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Two-factor authentication
                    @if ($user->hasTwoFactorEnabled())
                        <span class="badge text-bg-success ms-auto">On</span>
                    @elseif ($user->requiresTwoFactor())
                        <span class="badge text-bg-warning ms-auto">Required for your role</span>
                    @else
                        <span class="badge text-bg-secondary ms-auto">Off</span>
                    @endif
                </div>
                <div class="card-body">
                    <p class="text-secondary small">
                        When enabled, signing in also needs a 6-digit code from an authenticator app on your phone
                        (for example Google Authenticator or Microsoft Authenticator).
                    </p>

                    @if ($user->hasTwoFactorEnabled())
                        @if (in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
                            @if (session('status') === 'two-factor-authentication-confirmed')
                                <div class="alert alert-success small">
                                    <strong>Two-factor authentication is now on.</strong> From now on you'll enter a code from your authenticator app when you sign in.
                                </div>
                            @endif
                            <div class="alert alert-warning small">
                                <strong>Save these recovery codes</strong> somewhere safe. Each one lets you sign in once if you lose your phone.
                            </div>
                            <div class="bg-light border rounded p-3 mb-3 font-monospace small row row-cols-2 g-1 mx-0">
                                @foreach ($user->recoveryCodes() as $code)
                                    <div>{{ $code }}</div>
                                @endforeach
                            </div>
                            <a href="{{ route('dashboard') }}" class="btn btn-primary mb-3">I've saved these codes — continue to the dashboard</a>
                        @endif

                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Generate new recovery codes</button>
                            </form>
                            <form method="POST" action="{{ route('two-factor.disable') }}" data-confirm="Turn off two-factor authentication?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Turn off</button>
                            </form>
                        </div>
                    @elseif ($user->two_factor_secret)
                        <p class="small fw-semibold mb-2">1. Scan this QR code with your authenticator app</p>
                        <div class="mb-2 qr-code">{!! $user->twoFactorQrCodeSvg() !!}</div>
                        <p class="small text-secondary">Can't scan? Enter this key instead: <code class="user-select-all">{{ decrypt($user->two_factor_secret) }}</code></p>

                        <p class="small fw-semibold mb-2">2. Enter the 6-digit code it shows</p>
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="row g-2" novalidate>
                            @csrf
                            <div class="col-sm-6">
                                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" aria-label="Authentication code"
                                    @class(['form-control', 'is-invalid' => $errors->confirmTwoFactorAuthentication->has('code')])>
                                @error('code', 'confirmTwoFactorAuthentication')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-auto">
                                <button type="submit" class="btn btn-primary">Confirm</button>
                            </div>
                        </form>
                    @else
                        <form method="POST" action="{{ route('two-factor.enable') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Set up two-factor authentication</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
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
