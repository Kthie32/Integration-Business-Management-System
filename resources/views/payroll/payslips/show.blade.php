@inject('settings', App\Services\System\SettingsService::class)
@php($run = $item->payrollRun)
<x-layouts::app :title="'Payslip '.$item->employee_name.' · '.$run->periodLabel()">
    <x-page-header :title="'Payslip · '.$run->periodLabel()" :subtitle="$item->employee_name.' · '.$item->displayNumber()"
        :breadcrumbs="auth()->user()->can('view', $run)
            ? ['Payroll' => route('payroll-runs.index'), $run->periodLabel() => route('payroll-runs.show', $run), $item->employee_name => null]
            : ['My payslips' => route('payslips.mine'), $run->periodLabel() => null]">
        <x-slot:actions>
            <a href="{{ route('payslips.print', $item) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</a>
            <a href="{{ route('payslips.pdf', $item) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF</a>
        </x-slot:actions>
    </x-page-header>

    @unless ($run->isLocked())
        <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>This payroll is {{ strtolower($run->status->label()) }} and not approved yet; the figures may still change.</div>
    @endunless

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Earnings</div>
                        <ul class="list-group list-group-flush small">
                            @foreach ($item->earnings() as $line)
                                <li class="list-group-item d-flex"><span>{{ $line->description }}</span><x-money class="ms-auto" :amount="(string) $line->amount" /></li>
                            @endforeach
                            <li class="list-group-item d-flex fw-semibold"><span>Gross pay</span><x-money class="ms-auto" :amount="(string) $item->gross_pay" /></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Deductions</div>
                        <ul class="list-group list-group-flush small">
                            @foreach ($item->deductions() as $line)
                                <li class="list-group-item d-flex"><span>{{ $line->description }}</span><x-money class="ms-auto" :amount="(string) $line->amount" /></li>
                            @endforeach
                            <li class="list-group-item d-flex fw-semibold"><span>Total deductions</span><x-money class="ms-auto" :amount="(string) $item->total_deductions" /></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card mt-4 border-success-subtle">
                <div class="card-body d-flex align-items-center">
                    <span class="fs-5">Net pay</span>
                    <span class="ms-auto fs-4 fw-semibold"><x-money :amount="(string) $item->net_pay" /></span>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Employee</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Number</dt><dd class="col-7">{{ $item->employee_number }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Department</dt><dd class="col-7">{{ $item->department_name ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Job title</dt><dd class="col-7">{{ $item->job_title ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">TIN</dt><dd class="col-7">{{ $item->tin ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">NSSF no.</dt><dd class="col-7">{{ $item->nssf_number ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Paid by</dt><dd class="col-7">{{ $item->payment_method->label() }}@if ($item->paymentDestination() !== 'Cash')<div class="text-secondary">{{ $item->paymentDestination() }}</div>@endif</dd>
                        <dt class="col-5 text-secondary fw-normal">Status</dt><dd class="col-7"><x-status-badge :label="$item->payment_status->label()" :color="$item->payment_status->color()" />@if ($item->paid_at) {{ $settings->formatDate($item->paid_at) }}@endif</dd>
                    </dl>
                </div>
            </div>
            <div class="card">
                <div class="card-header fw-semibold">Tax &amp; contributions</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-7 text-secondary fw-normal">Taxable pay</dt><dd class="col-5 text-end"><x-money :amount="(string) $item->taxable_pay" /></dd>
                        <dt class="col-7 text-secondary fw-normal">Pay subject to NSSF</dt><dd class="col-5 text-end"><x-money :amount="(string) $item->nssf_pay" /></dd>
                        @foreach ($item->employerContributions() as $line)
                            <dt class="col-7 text-secondary fw-normal">{{ $line->description }}</dt><dd class="col-5 text-end"><x-money :amount="(string) $line->amount" /></dd>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
