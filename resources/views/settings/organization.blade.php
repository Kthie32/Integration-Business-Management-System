<x-layouts::app title="Organization settings">
    <x-page-header title="Settings" subtitle="The organization profile appears on documents, emails and throughout the system."
        :breadcrumbs="['Settings' => route('settings.index'), 'Organization' => null]" />

    @include('settings._nav')

    <form method="POST" action="{{ route('settings.organization.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Profile</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <x-form.input name="name" label="Organization name" :value="$organization['name']" required />
                            </div>
                            <div class="col-md-4">
                                <x-form.input name="tin" label="TIN" :value="$organization['tin']" />
                            </div>
                            <div class="col-12">
                                <x-form.textarea name="address" label="Address" :value="$organization['address']" rows="2" />
                            </div>
                            <div class="col-md-4">
                                <x-form.input name="phone" label="Phone" type="tel" :value="$organization['phone']" />
                            </div>
                            <div class="col-md-4">
                                <x-form.input name="email" label="Email" type="email" :value="$organization['email']" />
                            </div>
                            <div class="col-md-4">
                                <x-form.input name="website" label="Website" type="url" :value="$organization['website']" placeholder="https://" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header fw-semibold">Documents</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <x-form.textarea name="bank_details" label="Bank details" :value="$organization['bank_details']" rows="3"
                                    help="Printed on invoices so customers know where to pay." />
                            </div>
                            <div class="col-12">
                                <x-form.textarea name="document_footer" label="Document footer" :value="$organization['document_footer']" rows="2" />
                            </div>
                            <div class="col-md-6">
                                <x-form.input name="signatory_name" label="Signatory name" :value="$organization['signatory_name']" />
                            </div>
                            <div class="col-md-6">
                                <x-form.input name="signatory_title" label="Signatory title" :value="$organization['signatory_title']" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header fw-semibold">Branding</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-label">Current logo</div>
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Organization logo" class="settings-logo-preview">
                            @else
                                <div class="text-secondary small">No logo uploaded.</div>
                            @endif
                        </div>

                        <x-form.input name="logo" label="Upload a new logo" type="file" accept="image/png,image/jpeg,image/webp"
                            help="PNG, JPG or WebP, up to 1 MB." />

                        <div class="mb-3">
                            <label for="brand_color" class="form-label">Brand colour <span class="text-danger" aria-hidden="true">*</span></label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control form-control-color" id="brand_color_picker" value="{{ old('brand_color', $organization['brand_color']) }}"
                                    aria-label="Pick brand colour" data-color-sync="brand_color">
                                <input type="text" name="brand_color" id="brand_color" value="{{ old('brand_color', $organization['brand_color']) }}" maxlength="7"
                                    @class(['form-control', 'is-invalid' => $errors->has('brand_color')]) data-color-sync="brand_color_picker">
                            </div>
                            @error('brand_color')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Used for buttons, links and highlights.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save organization profile</button>
        </div>
    </form>

    @if ($logoUrl)
        <form method="POST" action="{{ route('settings.organization.logo.destroy') }}" class="mt-3" data-confirm="Remove the organization logo?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-link text-danger p-0">Remove logo</button>
        </form>
    @endif
</x-layouts::app>
