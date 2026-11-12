@inject('settings', App\Services\System\SettingsService::class)
@php($title = 'Payroll '.$run->periodLabel())
<x-layouts::app :title="$title">
    <x-page-header :title="$title" :subtitle="$run->displayNumber()"
        :breadcrumbs="['Payroll' => route('payroll-runs.index'), $run->periodLabel() => null]">
        <x-slot:actions>
            @can('prepare', $run)
                <form method="POST" action="{{ route('payroll-runs.calculate', $run) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-calculator me-1" aria-hidden="true"></i>{{ $run->calculated_at ? 'Recalculate' : 'Calculate' }}</button>
                </form>
            @endcan
            @can('submit', $run)
                <form method="POST" action="{{ route('payroll-runs.submit', $run) }}" data-confirm="Submit payroll for {{ $run->periodLabel() }} for approval? It can no longer be recalculated unless it is returned.">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1" aria-hidden="true"></i>Submit for approval</button>
                </form>
            @endcan
            @can('export', $run)
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1" aria-hidden="true"></i>Reports
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach (App\Services\Payroll\PayrollReportService::REPORTS as $key => $label)
                            <li><h6 class="dropdown-header">{{ $label }}</h6></li>
                            <li><a class="dropdown-item" href="{{ route('payroll-runs.export', [$run, $key, 'pdf']) }}"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF</a></li>
                            <li><a class="dropdown-item" href="{{ route('payroll-runs.export', [$run, $key, 'xlsx']) }}"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Excel</a></li>
                            <li><a class="dropdown-item" href="{{ route('payroll-runs.export', [$run, $key, 'print']) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</a></li>
                        @endforeach
                    </ul>
                </div>
            @endcan
            @can('delete', $run)
                <form method="POST" action="{{ route('payroll-runs.destroy', $run) }}" data-confirm="Delete the payroll run for {{ $run->periodLabel() }}?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($unverifiedRates->isNotEmpty() && ! $run->isLocked())
        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
            <strong>Unverified statutory rates:</strong> {{ $unverifiedRates->map(fn ($rate) => $rate->code->label())->implode(', ') }}.
            Check them against the current URA and NSSF publications and mark them verified before paying this payroll.
            @can('viewAny', App\Models\StatutoryRate::class)<a href="{{ route('statutory-rates.index') }}">Review statutory rates</a>@endcan
        </div>
    @endif

    @if ($run->status === App\Enums\PayrollRunStatus::PendingApproval)
        @can('decide', $run)
            <div class="card border-warning-subtle mb-4">
                <div class="card-body">
                    <h2 class="h6">Your decision — {{ $pendingStep?->name }}</h2>
                    <p class="small text-secondary">Approving locks the payroll and numbers the payslips (if this is the last step). Returning sends it back to be corrected and recalculated.</p>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <form method="POST" action="{{ route('payroll-runs.approve', $run) }}" data-confirm="Approve payroll for {{ $run->periodLabel() }}?" novalidate>
                                @csrf
                                <label for="approve_comment" class="form-label small">Comment (optional)</label>
                                <input type="text" name="comment" id="approve_comment" maxlength="1000" class="form-control mb-2">
                                <button type="submit" class="btn btn-success"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Approve</button>
                            </form>
                        </div>
                        <div class="col-lg-6">
                            <form method="POST" action="{{ route('payroll-runs.return', $run) }}" novalidate>
                                @csrf
                                <label for="return_comment" class="form-label small">What must be corrected? <span class="text-danger" aria-hidden="true">*</span></label>
                                <input type="text" name="comment" id="return_comment" maxlength="1000" class="form-control mb-2">
                                <button type="submit" class="btn btn-outline-info"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>Return for correction</button>
                            </form>
                        </div>
                    </div>
                    @error('comment')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>
            </div>
        @else
            <div class="alert alert-info small">
                <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                Waiting for <strong>{{ $pendingStep?->name ?? 'approval' }}</strong>@if ($waitingFor->isNotEmpty()) ({{ $waitingFor->implode(', ') }})@endif.
                Whoever created, calculated or submitted the run cannot approve it.
            </div>
        @endcan
    @endif

    @can('markPaid', $run)
        <div class="card border-success-subtle mb-4">
            <div class="card-body">
                <h2 class="h6">Record payment</h2>
                <form method="POST" action="{{ route('payroll-runs.mark-paid', $run) }}" class="row g-2 align-items-end" novalidate data-confirm="Mark payroll for {{ $run->periodLabel() }} as paid?">
                    @csrf
                    <div class="col-md-3">
                        <x-form.input name="paid_at" label="Paid on" type="date" :value="today()->toDateString()" required />
                    </div>
                    <div class="col-md-5">
                        <x-form.input name="payment_reference" label="Reference" maxlength="100" help="E.g. the bank batch or transfer reference." />
                    </div>
                    <div class="col-md-auto mb-3">
                        <button type="submit" class="btn btn-success"><i class="bi bi-cash-coin me-1" aria-hidden="true"></i>Mark as paid</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl">
            <x-stat-card label="Employees" :value="number_format($run->employee_count)" icon="people" />
        </div>
        <div class="col-sm-6 col-xl">
            <x-stat-card label="Gross pay" :value="$settings->formatMoney((string) $run->total_gross)" icon="cash" />
        </div>
        <div class="col-sm-6 col-xl">
            <x-stat-card label="Deductions" :value="$settings->formatMoney((string) $run->total_deductions)" icon="dash-circle" variant="danger"
                :hint="'PAYE '.$settings->formatMoney((string) $run->total_paye).' · NSSF '.$settings->formatMoney((string) $run->total_nssf_employee)" />
        </div>
        <div class="col-sm-6 col-xl">
            <x-stat-card label="Net pay" :value="$settings->formatMoney((string) $run->total_net)" icon="wallet2" variant="success" />
        </div>
        <div class="col-sm-6 col-xl">
            <x-stat-card label="Employer cost" :value="$settings->formatMoney((string) $run->total_employer_cost)" icon="building" variant="info"
                :hint="'incl. NSSF employer '.$settings->formatMoney((string) $run->total_nssf_employer)" />
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Payslips
                    <span class="ms-auto"><x-status-badge :status="$run->status" /></span>
                </div>
                @if ($run->items->isEmpty())
                    <div class="card-body small text-secondary">Not calculated yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Employee</th>
                                    <th scope="col" class="text-end">Gross</th>
                                    <th scope="col" class="text-end">PAYE</th>
                                    <th scope="col" class="text-end">NSSF</th>
                                    <th scope="col" class="text-end">LST</th>
                                    <th scope="col" class="text-end">Other</th>
                                    <th scope="col" class="text-end">Net pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($run->items as $item)
                                    <tr>
                                        <td>
                                            <a href="{{ route('payslips.show', $item) }}" class="text-decoration-none fw-semibold">{{ $item->employee_name }}</a>
                                            <div class="text-secondary">{{ $item->employee_number }}@if ($item->department_name) · {{ $item->department_name }}@endif @if ($item->payslip_number) · {{ $item->payslip_number }}@endif</div>
                                        </td>
                                        <td class="text-end"><x-money :amount="(string) $item->gross_pay" /></td>
                                        <td class="text-end"><x-money :amount="(string) $item->paye" /></td>
                                        <td class="text-end"><x-money :amount="(string) $item->nssf_employee" /></td>
                                        <td class="text-end"><x-money :amount="(string) $item->lst" /></td>
                                        <td class="text-end"><x-money :amount="(string) $item->other_deductions" /></td>
                                        <td class="text-end fw-semibold"><x-money :amount="(string) $item->net_pay" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end"><x-money :amount="(string) $run->total_gross" /></th>
                                    <th class="text-end"><x-money :amount="(string) $run->total_paye" /></th>
                                    <th class="text-end"><x-money :amount="(string) $run->total_nssf_employee" /></th>
                                    <th class="text-end"><x-money :amount="(string) $run->total_lst" /></th>
                                    <th class="text-end"><x-money :amount="(string) $run->total_other_deductions" /></th>
                                    <th class="text-end"><x-money :amount="(string) $run->total_net" /></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

            @if (! empty($run->skipped_employees))
                <div class="card mb-4 border-warning-subtle">
                    <div class="card-header fw-semibold">Not included ({{ count($run->skipped_employees) }})</div>
                    <ul class="list-group list-group-flush small">
                        @foreach ($run->skipped_employees as $skipped)
                            <li class="list-group-item">
                                {{ $skipped['name'] }} <span class="text-secondary">{{ $skipped['employee_number'] }}</span> — {{ $skipped['reason'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card mb-4" id="adjustments">
                <div class="card-header fw-semibold">One-off adjustments</div>
                @if ($run->adjustments->isEmpty())
                    <div class="card-body small text-secondary">None. Use adjustments for arrears, bonuses or recoveries, including corrections to an earlier approved month.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Employee</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Type</th>
                                    <th scope="col" class="text-end">Amount</th>
                                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($run->adjustments as $adjustment)
                                    <tr>
                                        <td>{{ $adjustment->employee?->fullName() }}</td>
                                        <td>
                                            {{ $adjustment->description }}
                                            <div class="text-secondary">
                                                {{ $adjustment->kind === App\Enums\PayComponentKind::Allowance
                                                    ? collect([$adjustment->is_taxable ? 'taxable' : 'not taxable', $adjustment->is_nssf_applicable ? 'NSSF applies' : 'no NSSF'])->implode(' · ')
                                                    : collect([$adjustment->is_taxable ? 'before tax' : 'after tax', $adjustment->is_nssf_applicable ? 'before NSSF' : null])->filter()->implode(' · ') }}
                                            </div>
                                        </td>
                                        <td>{{ $adjustment->kind->label() }}</td>
                                        <td class="text-end"><x-money :amount="(string) $adjustment->amount" /></td>
                                        <td class="text-end">
                                            @can('prepare', $run)
                                                <form method="POST" action="{{ route('payroll-runs.adjustments.destroy', [$run, $adjustment]) }}" data-confirm="Remove this adjustment?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Remove adjustment"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @can('prepare', $run)
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('payroll-runs.adjustments.store', $run) }}" novalidate>
                            @csrf
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <x-form.select name="employee_id" label="Employee" :options="$employees" placeholder="Choose…" required />
                                </div>
                                <div class="col-md-6">
                                    <x-form.input name="description" label="Description" maxlength="150" required />
                                </div>
                                <div class="col-md-4">
                                    <x-form.select name="kind" label="Type" :options="$adjustmentKinds" value="allowance" required />
                                </div>
                                <div class="col-md-4">
                                    <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" required />
                                </div>
                                <div class="col-md-4 pt-md-4">
                                    <x-form.checkbox name="is_taxable" label="Taxable / before tax" />
                                    <x-form.checkbox name="is_nssf_applicable" label="NSSF applies / before NSSF" />
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm">Add adjustment</button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Details</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Period</dt><dd class="col-7">{{ $run->periodLabel() }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Number</dt><dd class="col-7">{{ $run->number ?? 'On submission' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Payment date</dt><dd class="col-7">{{ $run->payment_date ? $settings->formatDate($run->payment_date) : '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Created by</dt><dd class="col-7">{{ $run->creator?->name ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Calculated</dt><dd class="col-7">{{ $run->calculated_at ? $settings->formatDate($run->calculated_at).' · '.$run->preparer?->name : '—' }}</dd>
                        @if ($run->submitted_at)
                            <dt class="col-5 text-secondary fw-normal">Submitted</dt><dd class="col-7">{{ $settings->formatDate($run->submitted_at) }} · {{ $run->submitter?->name }}</dd>
                        @endif
                        @if ($run->approved_at)
                            <dt class="col-5 text-secondary fw-normal">Approved</dt><dd class="col-7">{{ $settings->formatDate($run->approved_at) }} · {{ $run->approver?->name }}</dd>
                        @endif
                        @if ($run->paid_at)
                            <dt class="col-5 text-secondary fw-normal">Paid</dt><dd class="col-7">{{ $settings->formatDate($run->paid_at) }} · {{ $run->payer?->name }}@if ($run->payment_reference)<div class="text-secondary">{{ $run->payment_reference }}</div>@endif</dd>
                        @endif
                    </dl>
                    @if ($run->notes)
                        <hr>
                        <p class="small mb-0" style="white-space: pre-line">{{ $run->notes }}</p>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Approval trail</div>
                @if ($run->approvals->isEmpty())
                    <div class="card-body small text-secondary">Not submitted yet.</div>
                @else
                    <ul class="list-group list-group-flush small">
                        @foreach ($run->approvals as $approval)
                            <li class="list-group-item">
                                <div class="d-flex">
                                    <span>Round {{ $approval->round }} · {{ $approval->sequence }}. {{ $approval->name }}</span>
                                    <span class="ms-auto"><x-status-badge :label="$approval->status->label()" :color="$approval->status->color()" /></span>
                                </div>
                                <div class="text-secondary">
                                    {{ $approval->decider?->name ?? $approval->approverLabel() }}
                                    @if ($approval->decided_at) · {{ $settings->formatDate($approval->decided_at) }} {{ $approval->decided_at->format('H:i') }}@endif
                                </div>
                                @if ($approval->comment)<div>{{ $approval->comment }}</div>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <x-status-history :histories="$run->statusHistories" :labels="App\Enums\PayrollRunStatus::options()" />
        </div>
    </div>
</x-layouts::app>
