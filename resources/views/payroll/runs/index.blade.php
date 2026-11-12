@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Payroll runs">
    <x-page-header title="Payroll runs" subtitle="Monthly payroll: calculate, approve, pay and issue payslips."
        :breadcrumbs="['Payroll' => null]">
        <x-slot:actions>
            @can('create', App\Models\PayrollRun::class)
                <a href="{{ route('payroll-runs.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New payroll run</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('payroll-runs.index')" :reset-url="route('payroll-runs.index')">
        <div class="col-md-3">
            <label for="year" class="form-label small">Year</label>
            <input type="number" name="year" id="year" class="form-control" min="2000" max="2100" value="{{ $filters['year'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label small">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Any</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </x-filter-bar>

    <x-data-table :empty="$runs->isEmpty()" empty-message="No payroll runs yet." empty-icon="cash-stack" :paginator="$runs">
        <x-slot:head>
            <tr>
                <th scope="col">Period</th>
                <th scope="col">Run</th>
                <th scope="col" class="text-end">Employees</th>
                <th scope="col" class="text-end">Gross pay</th>
                <th scope="col" class="text-end">Net pay</th>
                <th scope="col">Prepared by</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($runs as $run)
            <tr>
                <td class="fw-semibold text-nowrap"><a href="{{ route('payroll-runs.show', $run) }}" class="text-decoration-none">{{ $run->periodLabel() }}</a></td>
                <td class="small text-nowrap">{{ $run->displayNumber() }}</td>
                <td class="text-end">{{ number_format($run->employee_count) }}</td>
                <td class="text-end"><x-money :amount="(string) $run->total_gross" /></td>
                <td class="text-end"><x-money :amount="(string) $run->total_net" /></td>
                <td class="small">{{ $run->preparer?->name ?? '—' }}</td>
                <td><x-status-badge :status="$run->status" /></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
