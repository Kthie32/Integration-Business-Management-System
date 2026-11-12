@inject('settings', App\Services\System\SettingsService::class)

<x-layouts::app :title="$employee->fullName()">
    <x-page-header :title="$employee->fullName()" :subtitle="$employee->employee_number.' · '.($employee->job_title ?? '')"
        :breadcrumbs="['Employees' => route('employees.index'), $employee->fullName() => null]">
        <x-slot:actions>
            <x-status-badge :status="$employee->status" class="align-self-center fs-6" />
            @can('update', $employee)
                <a href="{{ route('employees.edit', $employee) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Employment</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-5 text-secondary fw-normal">Department</dt><dd class="col-sm-7">{{ $employee->department?->name ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Job title</dt><dd class="col-sm-7">{{ $employee->job_title ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Employment type</dt><dd class="col-sm-7">{{ $employee->employment_type->label() }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Hire date</dt><dd class="col-sm-7">{{ $settings->formatDate($employee->hire_date) }}</dd>
                        @if ($employee->contract_end_date)
                            <dt class="col-sm-5 text-secondary fw-normal">Contract ends</dt><dd class="col-sm-7">{{ $settings->formatDate($employee->contract_end_date) }}</dd>
                        @endif
                        @if ($employee->termination_date)
                            <dt class="col-sm-5 text-secondary fw-normal">Terminated</dt>
                            <dd class="col-sm-7">{{ $settings->formatDate($employee->termination_date) }} — {{ $employee->termination_reason }}</dd>
                        @endif
                        <dt class="col-sm-5 text-secondary fw-normal">Tax residency</dt><dd class="col-sm-7">{{ $employee->is_resident_for_tax ? 'Resident' : 'Non-resident' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">User account</dt>
                        <dd class="col-sm-7">
                            @if ($employee->user)
                                @can('view', $employee->user)
                                    <a href="{{ route('admin.users.show', $employee->user) }}">{{ $employee->user->email }}</a>
                                @else
                                    {{ $employee->user->email }}
                                @endcan
                                @unless ($employee->user->is_active)<x-status-badge label="Inactive" color="secondary" class="ms-1" />@endunless
                            @else
                                None
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Personal &amp; contact</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-5 text-secondary fw-normal">Gender</dt><dd class="col-sm-7">{{ $employee->gender?->label() ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Date of birth</dt><dd class="col-sm-7">{{ $employee->date_of_birth ? $settings->formatDate($employee->date_of_birth) : '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Phone</dt><dd class="col-sm-7">{{ $employee->phone ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Email</dt><dd class="col-sm-7">{{ $employee->email ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Address</dt><dd class="col-sm-7">{{ $employee->address ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Next of kin</dt>
                        <dd class="col-sm-7">{{ collect([$employee->next_of_kin_name, $employee->next_of_kin_phone])->filter()->implode(' · ') ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        @if ($showSensitive)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold"><i class="bi bi-lock me-1" aria-hidden="true"></i>Identity &amp; payment details</div>
                    <div class="card-body">
                        <dl class="row small mb-0">
                            <dt class="col-sm-5 text-secondary fw-normal">National ID (NIN)</dt><dd class="col-sm-7">{{ $employee->nin ?? '—' }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">TIN</dt><dd class="col-sm-7">{{ $employee->tin ?? '—' }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">NSSF number</dt><dd class="col-sm-7">{{ $employee->nssf_number ?? '—' }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Bank</dt><dd class="col-sm-7">{{ collect([$employee->bank_name, $employee->bank_account])->filter()->implode(' · ') ?: '—' }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Mobile money</dt><dd class="col-sm-7">{{ $employee->mobile_money_number ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endif

        @if ($salaryStructures !== null)
            @include('employees._salary')
        @endif

        @can('changeStatus', $employee)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Change status</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('employees.status.update', $employee) }}" novalidate data-confirm="Change {{ $employee->fullName() }}'s employment status?">
                            @csrf
                            @method('PATCH')
                            <div class="row">
                                <div class="col-sm-6">
                                    <x-form.select name="status" label="New status" :options="$nextStatuses->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" required />
                                </div>
                                <div class="col-sm-6">
                                    <x-form.input name="effective_date" label="Effective date" type="date" :value="now()->toDateString()" required />
                                </div>
                            </div>
                            <x-form.input name="reason" label="Reason" maxlength="255" help="Required for termination. Termination is final." />
                            <button type="submit" class="btn btn-outline-primary">Change status</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Employment history</div>
                <ul class="list-group list-group-flush">
                    @forelse ($employee->employmentHistories as $history)
                        <li class="list-group-item small">
                            <span class="fw-semibold">{{ $history->fieldLabel() }}:</span>
                            {{ $history->old_value ?? '—' }} <i class="bi bi-arrow-right mx-1" aria-hidden="true"></i> {{ $history->new_value ?? '—' }}
                            <div class="text-secondary">
                                Effective {{ $settings->formatDate($history->effective_date) }} · {{ $history->recorder?->name ?? 'System' }}
                                @if ($history->notes) · {{ $history->notes }}@endif
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item small text-secondary">No changes recorded since registration.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-6">
            <x-status-history :histories="$employee->statusHistories"
                :labels="collect(App\Enums\EmployeeStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" class="h-100" />
        </div>

        @if ($documents !== null)
            <div class="col-12" id="documents">
                <div class="card">
                    <div class="card-header fw-semibold">Documents</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($documents as $document)
                            <li class="list-group-item d-flex flex-wrap align-items-center gap-3 small">
                                <i class="bi bi-file-earmark-text fs-4 text-secondary" aria-hidden="true"></i>
                                <div class="me-auto">
                                    <a href="{{ route('employees.documents.show', [$employee, $document]) }}" class="fw-semibold text-decoration-none">{{ $document->title }}</a>
                                    <span class="text-secondary">· {{ $document->document_type->label() }} · {{ $document->humanSize() }}</span>
                                    @if ($document->expires_at)
                                        <x-status-badge :label="($document->isExpired() ? 'Expired ' : 'Expires ').$settings->formatDate($document->expires_at)" :color="$document->isExpired() ? 'danger' : 'secondary'" class="ms-1" />
                                    @endif
                                    <div class="text-secondary">Uploaded {{ $settings->formatDate($document->created_at) }} by {{ $document->uploader?->name ?? 'unknown' }}</div>
                                </div>
                                @can('manageDocuments', $employee)
                                    <form method="POST" action="{{ route('employees.documents.destroy', [$employee, $document]) }}" data-confirm="Remove the document &quot;{{ $document->title }}&quot;?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                @endcan
                            </li>
                        @empty
                            <li class="list-group-item small text-secondary">No documents uploaded.</li>
                        @endforelse
                    </ul>

                    @can('manageDocuments', $employee)
                        <div class="card-body border-top">
                            <form method="POST" action="{{ route('employees.documents.store', $employee) }}" enctype="multipart/form-data" novalidate>
                                @csrf
                                <div class="row">
                                    <div class="col-md-3">
                                        <x-form.select name="document_type" label="Type"
                                            :options="collect(App\Enums\EmployeeDocumentType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()" required />
                                    </div>
                                    <div class="col-md-4">
                                        <x-form.input name="title" label="Title" maxlength="150" required />
                                    </div>
                                    <div class="col-md-2">
                                        <x-form.input name="expires_at" label="Expires" type="date" />
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.input name="file" label="File" type="file" accept=".pdf,.jpg,.jpeg,.png,.docx" required help="PDF, JPG, PNG or DOCX, up to 5 MB." />
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-upload me-1" aria-hidden="true"></i>Upload</button>
                            </form>
                        </div>
                    @endcan
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
