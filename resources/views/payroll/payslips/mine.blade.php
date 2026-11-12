@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="My payslips">
    <x-page-header title="My payslips" subtitle="Payslips from approved payroll runs."
        :breadcrumbs="['My payslips' => null]" />

    @unless ($linked)
        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Your user account is not linked to an employee record yet. Ask HR to link it to see your payslips.
        </div>
    @endunless

    <x-data-table :empty="$payslips->isEmpty()" empty-message="No payslips yet." empty-icon="file-earmark-text">
        <x-slot:head>
            <tr>
                <th scope="col">Month</th>
                <th scope="col">Payslip</th>
                <th scope="col" class="text-end">Gross pay</th>
                <th scope="col" class="text-end">Deductions</th>
                <th scope="col" class="text-end">Net pay</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="visually-hidden">Download</span></th>
            </tr>
        </x-slot:head>

        @foreach ($payslips as $payslip)
            <tr>
                <td class="fw-semibold"><a href="{{ route('payslips.show', $payslip) }}" class="text-decoration-none">{{ $payslip->payrollRun->periodLabel() }}</a></td>
                <td class="small">{{ $payslip->displayNumber() }}</td>
                <td class="text-end"><x-money :amount="(string) $payslip->gross_pay" /></td>
                <td class="text-end"><x-money :amount="(string) $payslip->total_deductions" /></td>
                <td class="text-end fw-semibold"><x-money :amount="(string) $payslip->net_pay" /></td>
                <td><x-status-badge :label="$payslip->payment_status->label()" :color="$payslip->payment_status->color()" /></td>
                <td class="text-end"><a href="{{ route('payslips.pdf', $payslip) }}" class="btn btn-sm btn-outline-secondary" aria-label="Download PDF"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></a></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
