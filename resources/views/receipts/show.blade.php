@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app :title="'Receipt '.$receipt->number">
    <x-page-header :title="'Receipt '.$receipt->number" :subtitle="$receipt->customer->name"
        :breadcrumbs="['Receipts' => route('receipts.index'), $receipt->number => null]">
        <x-slot:actions>
            <div class="btn-group">
                <a href="{{ route('receipts.pdf', $receipt) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF</a>
                <a href="{{ route('receipts.print', $receipt) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</a>
            </div>
            @can('reverse', $receipt)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reverseForm" aria-expanded="false" aria-controls="reverseForm">
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Reverse payment
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @can('reverse', $receipt)
        <div @class(['collapse mb-4', 'show' => $errors->has('reason')]) id="reverseForm">
            <div class="card card-body border-danger-subtle">
                <form method="POST" action="{{ route('receipts.reverse', $receipt) }}" class="row g-2 align-items-end" novalidate>
                    @csrf
                    <div class="col-md">
                        <x-form.input name="reason" label="Reason for reversing payment {{ $receipt->payment->number }}" maxlength="500" required
                            help="The payment is reversed, this receipt is voided and the invoice balance is restored. This cannot be undone." />
                    </div>
                    <div class="col-md-auto mb-3">
                        <button type="submit" class="btn btn-danger">Reverse payment</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @unless ($receipt->isValid())
        <div class="alert alert-secondary small">
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Voided {{ $settings->formatDate($receipt->voided_at) }}{{ $receipt->voider ? ' by '.$receipt->voider->name : '' }}: {{ $receipt->void_reason }}
        </div>
    @endunless

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Details
                    <span class="ms-auto"><x-status-badge :status="$receipt->status" /></span>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-4 text-secondary fw-normal">Amount received</dt><dd class="col-sm-8 fw-semibold fs-6"><x-money :amount="(string) $receipt->amount" /></dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Date</dt><dd class="col-sm-8">{{ $settings->formatDate($receipt->receipt_date) }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Customer</dt>
                        <dd class="col-sm-8">
                            @can('view', $receipt->customer)
                                <a href="{{ route('customers.show', $receipt->customer) }}">{{ $receipt->customer->name }}</a>
                            @else
                                {{ $receipt->customer->name }}
                            @endcan
                        </dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Invoice</dt>
                        <dd class="col-sm-8">
                            @can('view', $receipt->invoice)
                                <a href="{{ route('invoices.show', $receipt->invoice) }}">{{ $receipt->invoice->displayNumber() }}</a>
                            @else
                                {{ $receipt->invoice->displayNumber() }}
                            @endcan
                        </dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Payment</dt><dd class="col-sm-8">{{ $receipt->payment->number }} · <x-status-badge :status="$receipt->payment->status" /></dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Method</dt><dd class="col-sm-8">{{ $receipt->payment->method->label() }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Reference</dt><dd class="col-sm-8">{{ $receipt->payment->reference ?? '—' }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Received from</dt><dd class="col-sm-8">{{ $receipt->payment->payer_name ?? $receipt->customer->name }}</dd>
                        <dt class="col-sm-4 text-secondary fw-normal">Issued</dt><dd class="col-sm-8">{{ $settings->formatDate($receipt->issued_at) }} {{ $receipt->issued_at->format('H:i') }}{{ $receipt->issuer ? ' by '.$receipt->issuer->name : '' }}</dd>
                        @if ($receipt->payment->notes)
                            <dt class="col-sm-4 text-secondary fw-normal">Notes</dt><dd class="col-sm-8" style="white-space: pre-line">{{ $receipt->payment->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <x-status-history :histories="$receipt->statusHistories" :labels="App\Enums\ReceiptStatus::options()" />
        </div>
    </div>
</x-layouts::app>
