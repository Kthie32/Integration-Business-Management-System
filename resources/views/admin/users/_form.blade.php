<div class="row">
    <div class="col-md-6">
        <x-form.input name="name" label="Full name" :value="$user->name" autocomplete="off" required />
    </div>
    <div class="col-md-6">
        <x-form.input name="email" label="Email address" type="email" :value="$user->email" autocomplete="off" required />
    </div>
    <div class="col-md-6">
        <x-form.input name="phone" label="Phone number" type="tel" :value="$user->phone" autocomplete="off" />
    </div>
</div>

<fieldset class="mb-3">
    <legend class="form-label fs-6">Roles <span class="text-danger" aria-hidden="true">*</span></legend>
    @php($checkedRoles = array_map('intval', old('roles', $selectedRoles)))
    <div class="row row-cols-1 row-cols-md-2 g-2">
        @foreach ($roles as $role)
            <div class="col">
                <div class="form-check border rounded p-2 ps-5 h-100">
                    <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role-{{ $role->id }}" @checked(in_array($role->id, $checkedRoles, true))>
                    <label class="form-check-label" for="role-{{ $role->id }}">
                        <span class="fw-semibold">{{ $role->name }}</span>
                        @if ($role->description)
                            <span class="d-block small text-secondary">{{ $role->description }}</span>
                        @endif
                    </label>
                </div>
            </div>
        @endforeach
    </div>
    @error('roles')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
    @error('roles.*')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</fieldset>
