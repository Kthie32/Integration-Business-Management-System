<x-layouts::app :title="$supplier->name">
    <x-page-header :title="$supplier->name" :subtitle="$supplier->code"
        :breadcrumbs="['Suppliers' => route('suppliers.index'), $supplier->name => null]">
        <x-slot:actions>
            @can('update', $supplier)
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
            @can('delete', $supplier)
                <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" data-confirm="Delete {{ $supplier->name }}? This cannot be undone.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Details
                    <span class="ms-auto">
                        @if ($supplier->is_active)
                            <x-status-badge label="Active" color="success" />
                        @else
                            <x-status-badge label="Inactive" color="secondary" />
                        @endif
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-5 text-secondary fw-normal">TIN</dt><dd class="col-sm-7">{{ $supplier->tin ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Contact person</dt><dd class="col-sm-7">{{ $supplier->contact_person ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Phone</dt><dd class="col-sm-7">{{ $supplier->phone ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Email</dt><dd class="col-sm-7">{{ $supplier->email ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Address</dt><dd class="col-sm-7">{{ collect([$supplier->address, $supplier->city])->filter()->implode(', ') ?: '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Bank</dt><dd class="col-sm-7">{{ collect([$supplier->bank_name, $supplier->bank_account])->filter()->implode(' · ') ?: '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Mobile money</dt><dd class="col-sm-7">{{ $supplier->mobile_money_number ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Payment terms</dt><dd class="col-sm-7">{{ $supplier->payment_terms_days }} days</dd>
                    </dl>
                    @if ($supplier->notes)
                        <hr>
                        <p class="small mb-0" style="white-space: pre-line">{{ $supplier->notes }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Goods received
                    @can('create', App\Models\GoodsReceipt::class)
                        <a href="{{ route('goods-receipts.create', ['supplier' => $supplier->id]) }}" class="ms-auto small">Receive goods</a>
                    @endcan
                </div>
                @if ($goodsReceipts->isEmpty())
                    <div class="card-body text-center text-secondary py-5">
                        <i class="bi bi-box-seam fs-2 d-block mb-2" aria-hidden="true"></i>
                        No goods received from this supplier yet. Expenses and requisition fulfilments will appear here once those modules are live.
                    </div>
                @else
                    @inject('settings', App\Services\System\SettingsService::class)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr><th scope="col">GRN</th><th scope="col">Date</th><th scope="col">Warehouse</th><th scope="col" class="text-end">Total</th><th scope="col">Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($goodsReceipts as $receipt)
                                    <tr>
                                        <td><a href="{{ route('goods-receipts.show', $receipt) }}" class="text-decoration-none">{{ $receipt->displayNumber() }}</a></td>
                                        <td class="small text-nowrap">{{ $settings->formatDate($receipt->received_at) }}</td>
                                        <td class="small">{{ $receipt->warehouse->name }}</td>
                                        <td class="text-end"><x-money :amount="(string) $receipt->total" /></td>
                                        <td><x-status-badge :status="$receipt->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if ($activity->isNotEmpty())
            <div class="col-12">
                @include('partials.recent-activity', ['activity' => $activity])
            </div>
        @endif
    </div>
</x-layouts::app>
