@php($readOnly ??= false)
@php($checked = old('permissions', $selected))

<div class="row">
    <div class="col-md-5">
        <x-form.input name="name" label="Role name" :value="$role->name" required :readonly="$readOnly || $role->is_system"
            :help="$role->is_system ? 'System role names cannot be changed.' : null" />
    </div>
    <div class="col-md-7">
        <x-form.input name="description" label="Description" :value="$role->description" :readonly="$readOnly" />
    </div>
</div>

@error('permissions')
    <div class="alert alert-danger small">{{ $message }}</div>
@enderror
@error('permissions.*')
    <div class="alert alert-danger small">{{ $message }}</div>
@enderror

<div class="row row-cols-1 row-cols-lg-2 g-3 mb-4">
    @foreach ($groups as $group => $permissions)
        <div class="col">
            <fieldset class="border rounded p-3 h-100" data-permission-group>
                <legend class="float-none w-auto fs-6 fw-semibold px-1 mb-1 d-flex align-items-center gap-2">
                    {{ $group }}
                </legend>
                @unless ($readOnly)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="group-{{ $loop->index }}" data-select-all>
                        <label class="form-check-label small text-secondary" for="group-{{ $loop->index }}">Select all</label>
                    </div>
                @endunless
                @foreach ($permissions as $permission => $description)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}"
                            id="perm-{{ str_replace('.', '-', $permission) }}" @checked(in_array($permission, $checked, true)) @disabled($readOnly)>
                        <label class="form-check-label" for="perm-{{ str_replace('.', '-', $permission) }}">
                            {{ $description }}
                            <code class="small text-secondary ms-1">{{ $permission }}</code>
                        </label>
                    </div>
                @endforeach
            </fieldset>
        </div>
    @endforeach
</div>
