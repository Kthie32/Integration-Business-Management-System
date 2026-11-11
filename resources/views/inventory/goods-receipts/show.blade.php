@inject('settings', App\Services\System\SettingsService::class)
@php($quantity = App\Support\Quantity::class)
<x-layouts::app :title="$receipt->displayNumber()">
    <x-page-header :title="'Goods received '.$receipt->displayNumber()" :subtitle="$receipt->supplier->name"
        :breadcrumbs="['Goods received' => route('goods-receipts.index'), $receipt->displayNumber() => null]">
        <x-slot:actions>
            @can('post', $receipt)
                <form method="POST" action="{{ route('goods-receipts.post', $receipt) }}" data-confirm="Post this goods received note? Stock will be added immediately.">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Post to stock</button>
                </form>
            @endcan
            @can('update', $receipt)
                <a href="{{ route('goods-receipts.edit', $receipt) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
            @can('delete', $receipt)
                <form method="POST" action="{{ route('goods-receipts.destroy', $receipt) }}" data-confirm="Delete this draft?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete draft</button>
                </form>
            @endcan
            @can('void', $receipt)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#voidForm" aria-expanded="false" aria-controls="voidForm">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Void
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @can('void', $receipt)
        <div class="collapse mb-4" id="voidForm">
            <div class="card card-body border-danger-subtle">
                <form method="POST" action="{{ route('goods-receipts.void', $receipt) }}" class="row g-2 align-items-end" data-confirm="Void {{ $receipt->displayNumber() }} and reverse its stock?" novalidate>
                    @csrf
                    <div class="col-md">
                        <label for="reason" class="form-label">Reason for voiding <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" name="reason" id="reason" maxlength="500" required value="{{ old('reason') }}" @class(['form-control', 'is-invalid' => $errors->has('reason')])>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">The received quantities are taken back out of stock. This fails if some of the stock has already been issued.</div>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-danger">Void and reverse stock</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Items
                    <span class="ms-auto"><x-status-badge :status="$receipt->status" /></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th scope="col">Item</th><th scope="col" class="text-end">Quantity</th><th scope="col" class="text-end">Unit cost</th><th scope="col" class="text-end">Line total</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($receipt->items as $line)
                                <tr>
                                    <td>{{ $line->item->name }} <span class="small text-secondary"><code>{{ $line->item->code }}</code></span></td>
                                    <td class="text-end">{{ $quantity::format($line->quantity) }} {{ $line->item->unit }}</td>
                                    <td class="text-end"><x-money :amount="(string) $line->unit_cost" /></td>
                                    <td class="text-end"><x-money :amount="(string) $line->line_total" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-secondary py-4">No items.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold"><td colspan="3" class="text-end">Total</td><td class="text-end"><x-money :amount="(string) $receipt->total" /></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Details</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Supplier</dt>
                        <dd class="col-7">
                            @can('view', $receipt->supplier)
                                <a href="{{ route('suppliers.show', $receipt->supplier) }}" class="text-decoration-none">{{ $receipt->supplier->name }}</a>
                            @else
                                {{ $receipt->supplier->name }}
                            @endcan
                        </dd>
                        <dt class="col-5 text-secondary fw-normal">Warehouse</dt><dd class="col-7">{{ $receipt->warehouse->name }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Date received</dt><dd class="col-7">{{ $settings->formatDate($receipt->received_at) }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Supplier ref</dt><dd class="col-7">{{ $receipt->supplier_invoice_ref ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Prepared by</dt><dd class="col-7">{{ $receipt->creator?->name ?? '—' }}</dd>
                        @if ($receipt->posted_at)
                            <dt class="col-5 text-secondary fw-normal">Posted</dt><dd class="col-7">{{ $settings->formatDate($receipt->posted_at) }} by {{ $receipt->poster?->name }}</dd>
                        @endif
                        @if ($receipt->voided_at)
                            <dt class="col-5 text-secondary fw-normal">Voided</dt><dd class="col-7">{{ $settings->formatDate($receipt->voided_at) }} by {{ $receipt->voider?->name }}</dd>
                            <dt class="col-5 text-secondary fw-normal">Reason</dt><dd class="col-7">{{ $receipt->void_reason }}</dd>
                        @endif
                    </dl>
                    @if ($receipt->notes)
                        <hr>
                        <p class="small mb-0" style="white-space: pre-line">{{ $receipt->notes }}</p>
                    @endif
                </div>
            </div>

            <x-status-history :histories="$receipt->statusHistories" :labels="collect(App\Enums\GoodsReceiptStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
        </div>
    </div>
</x-layouts::app>
