@inject('settings', App\Services\System\SettingsService::class)
@php($quantity = App\Support\Quantity::class)
<x-layouts::app :title="$invoice->displayNumber()">
    <x-page-header :title="'Invoice '.$invoice->displayNumber()" :subtitle="$invoice->customer->name"
        :breadcrumbs="['Invoices' => route('invoices.index'), $invoice->displayNumber() => null]">
        <x-slot:actions>
            @can('issue', $invoice)
                <form method="POST" action="{{ route('invoices.issue', $invoice) }}"
                    data-confirm="Issue this invoice? It will be numbered and locked{{ $invoice->warehouse ? ', and stocked items will leave '.$invoice->warehouse->name : '' }}.">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send-check me-1" aria-hidden="true"></i>Issue</button>
                </form>
            @endcan
            @can('update', $invoice)
                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
            @can('recordPayment', $invoice)
                <button type="button" class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#paymentForm" aria-expanded="false" aria-controls="paymentForm">
                    <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>Record payment
                </button>
            @endcan
            @can('copy', $invoice)
                <form method="POST" action="{{ route('invoices.copy', $invoice) }}" data-confirm="Create a corrected draft from this voided invoice?">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-files me-1" aria-hidden="true"></i>Corrected copy</button>
                </form>
            @endcan
            <div class="btn-group">
                <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF</a>
                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</a>
            </div>
            @can('void', $invoice)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#voidForm" aria-expanded="false" aria-controls="voidForm">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Void
                </button>
            @endcan
            @can('delete', $invoice)
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" data-confirm="Delete this draft?{{ $invoice->quotation_id ? ' The quotation can then be invoiced again.' : '' }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete draft</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @can('recordPayment', $invoice)
        <div @class(['collapse mb-4', 'show' => old('_form') === 'paymentForm']) id="paymentForm">
            <div class="card card-body border-success-subtle">
                <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" novalidate>
                    @csrf
                    <input type="hidden" name="_form" value="paymentForm">
                    <div class="row g-2">
                        <div class="col-sm-6 col-lg-2">
                            <x-form.input name="payment_date" label="Date" type="date" :value="today()->toDateString()" required />
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="(string) $invoice->balance_due" required
                                :help="'Balance due: '.$settings->formatMoney((string) $invoice->balance_due)" />
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <x-form.select name="method" label="Method" :options="$paymentMethods" value="bank_transfer" required />
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <x-form.input name="reference" label="Transaction reference" maxlength="100" help="Required except for cash." />
                        </div>
                        <div class="col-lg-3">
                            <x-form.input name="payer_name" label="Paid by" maxlength="150" :value="$invoice->customer->contact_person" />
                        </div>
                        <div class="col-lg-9">
                            <x-form.input name="notes" label="Notes" maxlength="1000" />
                        </div>
                        <div class="col-lg-3 d-flex align-items-start pt-lg-4">
                            <button type="submit" class="btn btn-success mt-lg-2 w-100">Record payment</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @can('void', $invoice)
        <div @class(['collapse mb-4', 'show' => $errors->has('reason') && old('_form') === 'voidForm']) id="voidForm">
            <div class="card card-body border-danger-subtle">
                <form method="POST" action="{{ route('invoices.void', $invoice) }}" class="row g-2 align-items-end" novalidate>
                    @csrf
                    <input type="hidden" name="_form" value="voidForm">
                    <div class="col-md">
                        <label for="voidForm-text" class="form-label">Reason for voiding <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" name="reason" id="voidForm-text" maxlength="500" required value="{{ old('_form') === 'voidForm' ? old('reason') : '' }}"
                            @class(['form-control', 'is-invalid' => $errors->has('reason') && old('_form') === 'voidForm'])>
                        @if (old('_form') === 'voidForm')
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                        <div class="form-text">Voiding cannot be undone. Stock issued by this invoice returns to the warehouse; you can then create a corrected copy.</div>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-danger">Void invoice</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @if ($exceedsCreditLimit)
        <div class="alert alert-warning small"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Issuing this invoice takes {{ $invoice->customer->name }} past their credit limit of {{ $settings->formatMoney((string) $invoice->customer->credit_limit) }} (currently owing {{ $settings->formatMoney($invoice->customer->outstandingBalance()) }}).</div>
    @endif
    @if ($invoice->isOverdue())
        <div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>This invoice is {{ $invoice->daysOverdue() }} {{ Str::plural('day', $invoice->daysOverdue()) }} overdue.</div>
    @endif
    @if ($invoice->status === App\Enums\InvoiceStatus::Void)
        <div class="alert alert-secondary small">
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Voided {{ $settings->formatDate($invoice->voided_at) }}{{ $invoice->voider ? ' by '.$invoice->voider->name : '' }}: {{ $invoice->void_reason }}
            @foreach ($invoice->corrections as $correction)
                <div>Corrected by <a href="{{ route('invoices.show', $correction) }}">{{ $correction->displayNumber() }}</a>.</div>
            @endforeach
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header fw-semibold d-flex align-items-center gap-2">
                    Lines
                    <span class="ms-auto"><x-status-badge :status="$invoice->status" /></span>
                    @if ($invoice->isOverdue())
                        <x-status-badge label="Overdue" color="danger" />
                    @endif
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
                            @foreach ($invoice->items as $line)
                                <tr>
                                    <td>
                                        {{ $line->description }}
                                        @if ($line->item)
                                            <div class="small text-secondary"><code>{{ $line->item->code }}</code>@if ($line->item->isStocked()) · stocked @endif</div>
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
                            <tr><td colspan="5" class="text-end text-secondary">Subtotal</td><td class="text-end"><x-money :amount="(string) $invoice->subtotal" /></td></tr>
                            <tr><td colspan="5" class="text-end text-secondary">Discounts</td><td class="text-end"><x-money :amount="(string) $invoice->discount_total" /></td></tr>
                            <tr><td colspan="5" class="text-end text-secondary">Tax</td><td class="text-end"><x-money :amount="(string) $invoice->tax_total" /></td></tr>
                            <tr class="fw-semibold fs-6"><td colspan="5" class="text-end">Total</td><td class="text-end"><x-money :amount="(string) $invoice->total" /></td></tr>
                            @unless ($invoice->isDraft() || $invoice->status === App\Enums\InvoiceStatus::Void)
                                <tr><td colspan="5" class="text-end text-secondary">Paid</td><td class="text-end"><x-money :amount="(string) $invoice->amount_paid" /></td></tr>
                                <tr class="fw-semibold"><td colspan="5" class="text-end">Balance due</td><td @class(['text-end', 'text-danger' => $invoice->isOverdue()])><x-money :amount="(string) $invoice->balance_due" /></td></tr>
                            @endunless
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($payments !== null && ! $invoice->isDraft())
                <div class="card mt-4">
                    <div class="card-header fw-semibold">Payments</div>
                    @if ($payments->isEmpty())
                        <div class="card-body text-secondary small">No payments recorded yet.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th scope="col">Payment</th><th scope="col">Date</th><th scope="col">Method</th><th scope="col">Reference</th><th scope="col" class="text-end">Amount</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($payments as $payment)
                                        <tr>
                                            <td class="fw-semibold">{{ $payment->number }}<div class="small text-secondary fw-normal">by {{ $payment->recorder?->name ?? '—' }}</div></td>
                                            <td class="small text-nowrap">{{ $settings->formatDate($payment->payment_date) }}</td>
                                            <td class="small">{{ $payment->method->label() }}</td>
                                            <td class="small">{{ $payment->reference ?? '—' }}@if ($payment->payer_name)<div class="text-secondary">{{ $payment->payer_name }}</div>@endif</td>
                                            <td @class(['text-end', 'text-decoration-line-through text-secondary' => ! $payment->isCompleted()])><x-money :amount="(string) $payment->amount" /></td>
                                            <td>
                                                <x-status-badge :status="$payment->status" />
                                                @if ($payment->reversal_reason)
                                                    <div class="small text-secondary">{{ $payment->reversal_reason }}{{ $payment->reverser ? ' — '.$payment->reverser->name : '' }}</div>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @can('reverse', $payment)
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reverse-{{ $payment->id }}" aria-expanded="false" aria-controls="reverse-{{ $payment->id }}">Reverse</button>
                                                @endcan
                                            </td>
                                        </tr>
                                        @can('reverse', $payment)
                                            <tr @class(['collapse', 'show' => old('_form') === 'reverse-'.$payment->id]) id="reverse-{{ $payment->id }}">
                                                <td colspan="7" class="bg-body-tertiary">
                                                    <form method="POST" action="{{ route('payments.reverse', $payment) }}" class="row g-2 align-items-end" novalidate>
                                                        @csrf
                                                        <input type="hidden" name="_form" value="reverse-{{ $payment->id }}">
                                                        <div class="col-md">
                                                            <label for="reverse-{{ $payment->id }}-reason" class="form-label small">Reason for reversing {{ $payment->number }} <span class="text-danger" aria-hidden="true">*</span></label>
                                                            <input type="text" name="reason" id="reverse-{{ $payment->id }}-reason" maxlength="500" required
                                                                @class(['form-control form-control-sm', 'is-invalid' => $errors->has('reason') && old('_form') === 'reverse-'.$payment->id])>
                                                            @if (old('_form') === 'reverse-'.$payment->id)
                                                                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                            @endif
                                                        </div>
                                                        <div class="col-md-auto"><button type="submit" class="btn btn-sm btn-danger">Reverse payment</button></div>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endcan
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            @if ($stockMovements !== null && $stockMovements->isNotEmpty())
                <div class="card mt-4">
                    <div class="card-header fw-semibold">Stock movements</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th scope="col">Item</th><th scope="col">Warehouse</th><th scope="col">Movement</th><th scope="col" class="text-end">Quantity</th><th scope="col">When</th></tr></thead>
                            <tbody>
                                @foreach ($stockMovements as $movement)
                                    <tr>
                                        <td class="small">{{ $movement->item->name }}</td>
                                        <td class="small">{{ $movement->warehouse->name }}</td>
                                        <td class="small">{{ $movement->type->label() }}</td>
                                        <td class="text-end small">{{ $movement->direction === 1 ? '+' : '−' }}{{ $quantity::format($movement->quantity) }}</td>
                                        <td class="small text-nowrap">{{ $settings->formatDate($movement->moved_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($invoice->notes || $invoice->terms)
                <div class="card mt-4">
                    <div class="card-body small">
                        @if ($invoice->notes)
                            <h2 class="h6">Notes</h2>
                            <p style="white-space: pre-line">{{ $invoice->notes }}</p>
                        @endif
                        @if ($invoice->terms)
                            <h2 class="h6">Terms and conditions</h2>
                            <p class="mb-0" style="white-space: pre-line">{{ $invoice->terms }}</p>
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
                            @can('view', $invoice->customer)
                                <a href="{{ route('customers.show', $invoice->customer) }}" class="text-decoration-none">{{ $invoice->customer->name }}</a>
                            @else
                                {{ $invoice->customer->name }}
                            @endcan
                        </dd>
                        <dt class="col-5 text-secondary fw-normal">Invoice date</dt><dd class="col-7">{{ $settings->formatDate($invoice->invoice_date) }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Due date</dt><dd @class(['col-7', 'text-danger fw-semibold' => $invoice->isOverdue()])>{{ $settings->formatDate($invoice->due_date) }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Warehouse</dt><dd class="col-7">{{ $invoice->warehouse?->name ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Currency</dt><dd class="col-7">{{ $invoice->currency }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Prepared by</dt><dd class="col-7">{{ $invoice->creator?->name ?? '—' }}</dd>
                        @if ($invoice->issued_at)
                            <dt class="col-5 text-secondary fw-normal">Issued</dt><dd class="col-7">{{ $settings->formatDate($invoice->issued_at) }}{{ $invoice->issuer ? ' by '.$invoice->issuer->name : '' }}</dd>
                        @endif
                        @if ($invoice->quotation)
                            <dt class="col-5 text-secondary fw-normal">Quotation</dt>
                            <dd class="col-7">
                                @can('view', $invoice->quotation)
                                    <a href="{{ route('quotations.show', $invoice->quotation) }}" class="text-decoration-none">{{ $invoice->quotation->displayNumber() }}</a>
                                @else
                                    {{ $invoice->quotation->displayNumber() }}
                                @endcan
                            </dd>
                        @endif
                        @if ($invoice->copiedFrom)
                            <dt class="col-5 text-secondary fw-normal">Corrects</dt>
                            <dd class="col-7"><a href="{{ route('invoices.show', $invoice->copiedFrom) }}" class="text-decoration-none">{{ $invoice->copiedFrom->displayNumber() }}</a></dd>
                        @endif
                    </dl>
                </div>
            </div>

            <x-status-history :histories="$invoice->statusHistories" :labels="App\Enums\InvoiceStatus::options()" />
        </div>
    </div>
</x-layouts::app>
