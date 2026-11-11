@inject('settings', App\Services\System\SettingsService::class)
@php($quantity = App\Support\Quantity::class)
<x-layouts::app :title="$quotation->displayNumber()">
    <x-page-header :title="'Quotation '.$quotation->displayNumber()" :subtitle="$quotation->customer->name"
        :breadcrumbs="['Quotations' => route('quotations.index'), $quotation->displayNumber() => null]">
        <x-slot:actions>
            @can('submit', $quotation)
                <form method="POST" action="{{ route('quotations.submit', $quotation) }}"
                    data-confirm="{{ $requiresApproval ? 'Submit this quotation for approval?' : 'Finalise this quotation? It will get its number and can then be sent.' }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send-check me-1" aria-hidden="true"></i>{{ $requiresApproval ? 'Submit for approval' : 'Finalise' }}</button>
                </form>
            @endcan
            @can('update', $quotation)
                <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
            @can('approve', $quotation)
                <form method="POST" action="{{ route('quotations.approve', $quotation) }}" data-confirm="Approve this quotation?">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Approve</button>
                </form>
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="collapse" data-bs-target="#returnForm" aria-expanded="false" aria-controls="returnForm">
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Return for changes
                </button>
            @endcan
            @can('markSent', $quotation)
                <form method="POST" action="{{ route('quotations.sent', $quotation) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-envelope-check me-1" aria-hidden="true"></i>Mark as sent</button>
                </form>
            @endcan
            @can('recordDecision', $quotation)
                <form method="POST" action="{{ route('quotations.accept', $quotation) }}" data-confirm="Record that the customer accepted this quotation?">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="bi bi-hand-thumbs-up me-1" aria-hidden="true"></i>Accepted</button>
                </form>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rejectForm" aria-expanded="false" aria-controls="rejectForm">
                    <i class="bi bi-hand-thumbs-down me-1" aria-hidden="true"></i>Rejected
                </button>
            @endcan
            @can('revise', $quotation)
                <form method="POST" action="{{ route('quotations.revise', $quotation) }}" data-confirm="Prepare a new revision of this quotation?">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-files me-1" aria-hidden="true"></i>Revise</button>
                </form>
            @endcan
            <div class="btn-group">
                <a href="{{ route('quotations.pdf', $quotation) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF</a>
                <a href="{{ route('quotations.print', $quotation) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</a>
            </div>
            @can('cancel', $quotation)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancelForm" aria-expanded="false" aria-controls="cancelForm">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancel
                </button>
            @endcan
            @can('delete', $quotation)
                <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" data-confirm="Delete this draft?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete draft</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @foreach ([
        'returnForm' => ['ability' => 'approve', 'route' => 'quotations.return', 'field' => 'comment', 'label' => 'What needs to change?', 'button' => 'Return to author', 'tone' => 'warning'],
        'rejectForm' => ['ability' => 'recordDecision', 'route' => 'quotations.reject', 'field' => 'reason', 'label' => 'Why did the customer reject it?', 'button' => 'Record rejection', 'tone' => 'danger'],
        'cancelForm' => ['ability' => 'cancel', 'route' => 'quotations.cancel', 'field' => 'reason', 'label' => 'Reason for cancelling', 'button' => 'Cancel quotation', 'tone' => 'danger'],
    ] as $formId => $form)
        @can($form['ability'], $quotation)
            <div @class(['collapse mb-4', 'show' => $errors->has($form['field']) && old('_form') === $formId]) id="{{ $formId }}">
                <div class="card card-body border-{{ $form['tone'] }}-subtle">
                    <form method="POST" action="{{ route($form['route'], $quotation) }}" class="row g-2 align-items-end" novalidate>
                        @csrf
                        <input type="hidden" name="_form" value="{{ $formId }}">
                        <div class="col-md">
                            <label for="{{ $formId }}-text" class="form-label">{{ $form['label'] }} <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" name="{{ $form['field'] }}" id="{{ $formId }}-text" maxlength="500" required
                                value="{{ old('_form') === $formId ? old($form['field']) : '' }}"
                                @class(['form-control', 'is-invalid' => $errors->has($form['field']) && old('_form') === $formId])>
                            @if (old('_form') === $formId)
                                @error($form['field'])<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>
                        <div class="col-md-auto">
                            <button type="submit" class="btn btn-{{ $form['tone'] }}">{{ $form['button'] }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endforeach

    @if ($quotation->isDraft() && $requiresApproval)
        <div class="alert alert-info small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>This total reaches the approval threshold, so someone other than the author must approve it after submission.</div>
    @endif
    @if ($quotation->revisionChild)
        <div class="alert alert-secondary small">
            <i class="bi bi-files me-1" aria-hidden="true"></i>
            Revision <a href="{{ route('quotations.show', $quotation->revisionChild) }}">{{ $quotation->revisionChild->displayNumber() }}</a>
            {{ $quotation->revisionChild->isDraft() ? 'is being prepared.' : 'replaces this quotation.' }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Lines
                    <span class="ms-auto"><x-status-badge :status="$quotation->status" /></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Description</th>
                                <th scope="col" class="text-end">Qty</th>
                                <th scope="col" class="text-end">Unit price</th>
                                <th scope="col" class="text-end">Discount</th>
                                <th scope="col" class="text-end">Tax</th>
                                <th scope="col" class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quotation->items as $line)
                                <tr>
                                    <td>
                                        {{ $line->description }}
                                        @if ($line->item)
                                            <div class="small text-secondary"><code>{{ $line->item->code }}</code></div>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">{{ $quantity::format($line->quantity) }} {{ $line->unit }}</td>
                                    <td class="text-end"><x-money :amount="(string) $line->unit_price" /></td>
                                    <td class="text-end"><x-money :amount="App\Support\Money::of((string) $line->discount_amount)->plus((string) $line->document_discount_share)->toDecimal()" /></td>
                                    <td class="text-end small text-nowrap">{{ rtrim(rtrim((string) $line->tax_rate, '0'), '.') }} %<div class="text-secondary"><x-money :amount="(string) $line->tax_amount" /></div></td>
                                    <td class="text-end"><x-money :amount="(string) $line->line_total" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr><td colspan="5" class="text-end text-secondary">Subtotal</td><td class="text-end"><x-money :amount="(string) $quotation->subtotal" /></td></tr>
                            <tr><td colspan="5" class="text-end text-secondary">Discounts</td><td class="text-end"><x-money :amount="(string) $quotation->discount_total" /></td></tr>
                            <tr><td colspan="5" class="text-end text-secondary">Tax</td><td class="text-end"><x-money :amount="(string) $quotation->tax_total" /></td></tr>
                            <tr class="fw-semibold fs-6"><td colspan="5" class="text-end">Total</td><td class="text-end"><x-money :amount="(string) $quotation->total" /></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($quotation->notes || $quotation->terms)
                <div class="card mt-4">
                    <div class="card-body small">
                        @if ($quotation->notes)
                            <h2 class="h6">Notes</h2>
                            <p style="white-space: pre-line">{{ $quotation->notes }}</p>
                        @endif
                        @if ($quotation->terms)
                            <h2 class="h6">Terms and conditions</h2>
                            <p class="mb-0" style="white-space: pre-line">{{ $quotation->terms }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Details</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Customer</dt>
                        <dd class="col-7">
                            @can('view', $quotation->customer)
                                <a href="{{ route('customers.show', $quotation->customer) }}" class="text-decoration-none">{{ $quotation->customer->name }}</a>
                            @else
                                {{ $quotation->customer->name }}
                            @endcan
                        </dd>
                        <dt class="col-5 text-secondary fw-normal">Date</dt><dd class="col-7">{{ $settings->formatDate($quotation->quote_date) }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Valid until</dt>
                        <dd @class(['col-7', 'text-danger' => $quotation->valid_until->lt(today()) && ! in_array($quotation->status, [App\Enums\QuotationStatus::Accepted, App\Enums\QuotationStatus::Cancelled, App\Enums\QuotationStatus::Superseded], true)])>{{ $settings->formatDate($quotation->valid_until) }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Currency</dt><dd class="col-7">{{ $quotation->currency }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Prepared by</dt><dd class="col-7">{{ $quotation->creator?->name ?? '—' }}</dd>
                        @if ($quotation->approved_at)
                            <dt class="col-5 text-secondary fw-normal">Approved</dt>
                            <dd class="col-7">{{ $settings->formatDate($quotation->approved_at) }}{{ $quotation->approver ? ' by '.$quotation->approver->name : ' (no approval needed)' }}</dd>
                        @endif
                        @if ($quotation->sent_at)
                            <dt class="col-5 text-secondary fw-normal">Sent</dt><dd class="col-7">{{ $settings->formatDate($quotation->sent_at) }}</dd>
                        @endif
                        @if ($quotation->accepted_at)
                            <dt class="col-5 text-secondary fw-normal">Accepted</dt><dd class="col-7">{{ $settings->formatDate($quotation->accepted_at) }}</dd>
                        @endif
                        @if ($quotation->converted_at)
                            <dt class="col-5 text-secondary fw-normal">Invoiced</dt><dd class="col-7">{{ $settings->formatDate($quotation->converted_at) }} by {{ $quotation->converter?->name }}</dd>
                        @endif
                        @if ($quotation->parent)
                            <dt class="col-5 text-secondary fw-normal">Revises</dt>
                            <dd class="col-7"><a href="{{ route('quotations.show', $quotation->parent) }}" class="text-decoration-none">{{ $quotation->parent->displayNumber() }}</a></dd>
                        @endif
                    </dl>
                </div>
            </div>

            <x-status-history :histories="$quotation->statusHistories" :labels="App\Enums\QuotationStatus::options()" />
        </div>
    </div>
</x-layouts::app>
