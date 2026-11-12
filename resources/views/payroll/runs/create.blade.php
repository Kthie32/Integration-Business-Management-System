<x-layouts::app title="New payroll run">
    <x-page-header title="New payroll run" subtitle="One regular run per month. You can calculate it as often as you need until it is submitted."
        :breadcrumbs="['Payroll' => route('payroll-runs.index'), 'New run' => null]" />

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('payroll-runs.store') }}" novalidate>
                        @csrf
                        <div class="row">
                            <div class="col-sm-6">
                                <x-form.select name="period_month" label="Month" :options="$months" :value="$defaultMonth" required />
                            </div>
                            <div class="col-sm-6">
                                <x-form.input name="period_year" label="Year" type="number" min="2000" max="2100" :value="$defaultYear" required />
                            </div>
                        </div>
                        <x-form.input name="payment_date" label="Planned payment date" type="date" help="Optional. Shown on payslips." />
                        <x-form.textarea name="notes" label="Notes" rows="2" maxlength="1000" />
                        <button type="submit" class="btn btn-primary">Create run</button>
                        <a href="{{ route('payroll-runs.index') }}" class="btn btn-link">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="alert alert-info small">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                Everyone hired by the end of the month who is not suspended or terminated before it began is paid, using the salary structure in effect on the last day of the month.
                Employees without a salary structure are listed as skipped. Pay is not pro-rated for part months — use an adjustment instead.
            </div>
        </div>
    </div>
</x-layouts::app>
