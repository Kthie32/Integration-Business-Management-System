@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Statutory rates">
    <x-page-header title="Statutory rates" subtitle="PAYE, NSSF and LST rules by effective date. Payroll uses the version in effect on the last day of the month."
        :breadcrumbs="['Payroll' => route('payroll-runs.index'), 'Statutory rates' => null]" />

    <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
        The seeded rates are the developer's understanding and are <strong>unverified</strong>. Check each one against the current URA and NSSF publications and mark it verified before the first live payroll.
    </div>

    @foreach ($groups as $group)
        <div class="card mb-4">
            <div class="card-header fw-semibold d-flex align-items-center">
                {{ $group['code']->label() }}
                @can('create', App\Models\StatutoryRate::class)
                    <a href="{{ route('statutory-rates.create', ['code' => $group['code']->value]) }}" class="btn btn-sm btn-outline-primary ms-auto"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New version</a>
                @endcan
            </div>
            @if ($group['versions']->isEmpty())
                <div class="card-body small text-secondary">
                    Not configured.
                    @if ($group['code'] === App\Enums\StatutoryRateCode::PayeNonResident) Needed only for employees who are not resident for tax. @endif
                    @if ($group['code'] === App\Enums\StatutoryRateCode::Lst) Without it no LST is deducted. @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Effective</th>
                                <th scope="col">Rules</th>
                                <th scope="col">Verification</th>
                                <th scope="col"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['versions'] as $rate)
                                <tr>
                                    <td class="text-nowrap">
                                        {{ $settings->formatDate($rate->effective_from) }} – {{ $rate->effective_to ? $settings->formatDate($rate->effective_to) : 'now' }}
                                        @if ($rate->notes)<div class="text-secondary" style="max-width: 18rem; white-space: normal">{{ $rate->notes }}</div>@endif
                                    </td>
                                    <td>@include('payroll.statutory._summary', ['rate' => $rate])</td>
                                    <td>
                                        @if ($rate->isVerified())
                                            <x-status-badge label="Verified" color="success" />
                                            <div class="text-secondary">{{ $rate->verifier?->name }} · {{ $settings->formatDate($rate->verified_at) }}</div>
                                            @if ($rate->source_reference)<div class="text-secondary">{{ $rate->source_reference }}</div>@endif
                                        @else
                                            <x-status-badge label="Unverified" color="warning" />
                                            @can('verify', $rate)
                                                <form method="POST" action="{{ route('statutory-rates.verify', $rate) }}" class="mt-2" novalidate data-confirm="Confirm you checked these rules against the official publication?">
                                                    @csrf
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="source_reference" class="form-control @error('source_reference') is-invalid @enderror" placeholder="Source, e.g. URA PAYE guide 2026" maxlength="255" aria-label="Source checked" value="{{ $rate->source_reference }}">
                                                        <button type="submit" class="btn btn-outline-success">Mark verified</button>
                                                    </div>
                                                </form>
                                            @endcan
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @can('update', $rate)
                                            <a href="{{ route('statutory-rates.edit', $rate) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        @else
                                            @if ($rate->isLocked())<span class="text-secondary"><i class="bi bi-lock" aria-hidden="true"></i> Used by approved payroll</span>@endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
</x-layouts::app>
