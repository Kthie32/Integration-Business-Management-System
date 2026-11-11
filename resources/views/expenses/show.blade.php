@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app :title="'Expense '.$expense->displayNumber()">
    <x-page-header :title="'Expense '.$expense->displayNumber()" :subtitle="$expense->category->name"
        :breadcrumbs="['Expenses' => route('expenses.index'), $expense->displayNumber() => null]">
        <x-slot:actions>
            @can('record', $expense)
                <form method="POST" action="{{ route('expenses.record', $expense) }}" data-confirm="Record this expense? It will be numbered and can then only be voided.">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Record</button>
                </form>
            @endcan
            @can('update', $expense)
                <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
            @can('void', $expense)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#voidForm" aria-expanded="false" aria-controls="voidForm">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Void
                </button>
            @endcan
            @can('delete', $expense)
                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" data-confirm="Delete this draft expense?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @can('void', $expense)
        <div @class(['collapse mb-4', 'show' => $errors->has('reason')]) id="voidForm">
            <div class="card card-body border-danger-subtle">
                <form method="POST" action="{{ route('expenses.void', $expense) }}" class="row g-2 align-items-end" novalidate>
                    @csrf
                    <div class="col-md">
                        <x-form.input name="reason" label="Reason for voiding" maxlength="500" required help="Voiding cannot be undone. The expense stays on record, marked void." />
                    </div>
                    <div class="col-md-auto mb-3">
                        <button type="submit" class="btn btn-danger">Void expense</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @if ($expense->status === App\Enums\ExpenseStatus::Void)
        <div class="alert alert-secondary small">
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Voided {{ $settings->formatDate($expense->voided_at) }}{{ $expense->voider ? ' by '.$expense->voider->name : '' }}: {{ $expense->void_reason }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Details
                    <span class="ms-auto"><x-status-badge :status="$expense->status" /></span>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-4 text-secondary fw-normal">Date</dt><dd class="col-sm-8">{{ $settings->formatDate($expense->expense_date) }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Description</dt><dd class="col-sm-8">{{ $expense->description }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Category</dt><dd class="col-sm-8">{{ $expense->category->name }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Paid to</dt>
                        <dd class="col-sm-8">
                            @if ($expense->supplier)
                                @can('view', $expense->supplier)
                                    <a href="{{ route('suppliers.show', $expense->supplier) }}">{{ $expense->supplier->name }}</a>
                                @else
                                    {{ $expense->supplier->name }}
                                @endcan
                                @if ($expense->payee_name)<div class="text-secondary">{{ $expense->payee_name }}</div>@endif
                            @else
                                {{ $expense->payee_name ?? '—' }}
                            @endif
                        </dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Department</dt><dd class="col-sm-8">{{ $expense->department?->name ?? 'Organization-wide' }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Amount before tax</dt><dd class="col-sm-8"><x-money :amount="$expense->netAmount()" /></dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Tax</dt><dd class="col-sm-8"><x-money :amount="(string) $expense->tax_amount" /></dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Total</dt><dd class="col-sm-8 fw-semibold fs-6"><x-money :amount="(string) $expense->amount" /></dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Paid by</dt><dd class="col-sm-8">{{ $expense->payment_method?->label() ?? '—' }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Reference</dt><dd class="col-sm-8">{{ $expense->reference ?? '—' }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Attachment</dt>
                        <dd class="col-sm-8">
                            @if ($expense->hasAttachment())
                                <a href="{{ route('expenses.attachment', $expense) }}"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ $expense->attachment_name }}</a>
                                <span class="text-secondary">({{ Illuminate\Support\Number::fileSize($expense->attachment_size ?? 0) }})</span>
                            @else
                                —
                            @endif
                        </dd>
                        @if ($expense->notes)
                            <dt class="col-sm-4 text-secondary fw-normal">Notes</dt><dd class="col-sm-8" style="white-space: pre-line">{{ $expense->notes }}</dd>
                        @endif
                        <dt class="col-sm-4 text-secondary fw-normal">Entered by</dt><dd class="col-sm-8">{{ $expense->creator?->name ?? '—' }}</dd>
                        @if ($expense->recorded_at)
                            <dt class="col-sm-4 text-secondary fw-normal">Recorded</dt><dd class="col-sm-8">{{ $settings->formatDate($expense->recorded_at) }}{{ $expense->recorder ? ' by '.$expense->recorder->name : '' }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <x-status-history :histories="$expense->statusHistories" :labels="App\Enums\ExpenseStatus::options()" />
        </div>
    </div>
</x-layouts::app>
