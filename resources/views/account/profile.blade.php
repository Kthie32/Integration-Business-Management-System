<x-layouts::app title="My profile">
    <x-page-header title="My profile" subtitle="Your contact details and password." />

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Contact details</div>
                <div class="card-body">
                    @if (session('status') === 'profile-information-updated')
                        <div class="alert alert-success small" role="status">Profile updated.</div>
                    @endif

                    <form method="POST" action="{{ route('user-profile-information.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <x-form.input name="name" label="Full name" :value="$user->name" bag="updateProfileInformation" autocomplete="name" required />
                        <x-form.input name="email" label="Email address" type="email" :value="$user->email" bag="updateProfileInformation" autocomplete="email" required />
                        <x-form.input name="phone" label="Phone number" type="tel" :value="$user->phone" bag="updateProfileInformation" autocomplete="tel" />

                        <div class="mb-3">
                            <div class="form-label">Roles</div>
                            @forelse ($user->roles as $role)
                                <span class="badge text-bg-light border">{{ $role->name }}</span>
                            @empty
                                <span class="text-secondary small">None</span>
                            @endforelse
                            <div class="form-text">Roles are assigned by an administrator.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Save details</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Change password</div>
                <div class="card-body">
                    @if (session('status') === 'password-updated')
                        <div class="alert alert-success small" role="status">Password changed.</div>
                    @endif

                    <form method="POST" action="{{ route('user-password.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <x-form.input name="current_password" id="current_password" label="Current password" type="password" bag="updatePassword" autocomplete="current-password" required />
                        <x-form.input name="password" id="new_password" label="New password" type="password" bag="updatePassword" autocomplete="new-password" help="At least 8 characters, with upper- and lower-case letters and a number." required />
                        <x-form.input name="password_confirmation" id="new_password_confirmation" label="Confirm new password" type="password" bag="updatePassword" autocomplete="new-password" required />

                        <button type="submit" class="btn btn-primary">Change password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
