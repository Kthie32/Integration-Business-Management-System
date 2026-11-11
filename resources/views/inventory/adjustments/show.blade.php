@inject('settings', App\Services\System\SettingsService::class)
@php($quantity = App\Support\Quantity::class)
<x-layouts::app :title="$adjustment->number">
    <x-page-header :title="'Stock adjustment '.$adjustment->number" :subtitle="$adjustment->warehouse->name"
        :breadcrumbs="['Stock adjustments' => route('stock-adjustments.index'), $adjustment->number => null]">
        <x-slot:actions>
            @can('cancel', $adjustment)
                <form method="POST" action="{{ route('stock-adjustments.cancel', $adjustment) }}" data-confirm="Cancel {{ $adjustment->number }}?">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">Cancel adjustment</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($adjustment->isPending())
        @can('decide', $adjustment)
            <div class="card border-warning-subtle mb-4">
                <div class="card-body">
                    <h2 class="h6">Your decision</h2>
                    <p class="small text-secondary">Approving changes stock immediately. A decrease fails if the warehouse no longer holds enough.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('stock-adjustments.approve', $adjustment) }}" data-confirm="Approve {{ $adjustment->number }} and update stock?" novalidate>
                                @csrf
                                <label for="approve_comment" class="form-label small">Comment (optional)</label>
                                <input type="text" name="comment" id="approve_comment" maxlength="500" class="form-control mb-2">
                                <button type="submit" class="btn btn-success"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Approve</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('stock-adjustments.reject', $adjustment) }}" novalidate>
                                @csrf
                                <label for="reject_comment" class="form-label small">Reason for rejecting <span class="text-danger" aria-hidden="true">*</span></label>
                                <input type="text" name="comment" id="reject_comment" maxlength="500" value="{{ old('comment') }}" @class(['form-control mb-2', 'is-invalid' => $errors->has('comment')])>
                                @error('comment')<div class="invalid-feedback mb-2">{{ $message }}</div>@enderror
                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x me-1" aria-hidden="true"></i>Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($adjustment->created_by === auth()->id())
            <div class="alert alert-info small">This adjustment is waiting for another authorised person to approve it.</div>
        @endcan
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Items
                    <span class="ms-auto"><x-status-badge :status="$adjustment->status" /></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col" class="text-end">Change</th>
                                <th scope="col" class="text-end">Unit cost</th>
                                @if ($adjustment->isPending())<th scope="col" class="text-end">Now in stock</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($adjustment->items as $line)
                                <tr>
                                    <td>{{ $line->item->name }} <span class="small text-secondary"><code>{{ $line->item->code }}</code></span></td>
                                    <td @class(['text-end fw-semibold', 'text-success' => $line->isIncrease(), 'text-danger' => ! $line->isIncrease()])>
                                        {{ $line->isIncrease() ? '+' : '' }}{{ $quantity::format($line->quantity_change) }} {{ $line->item->unit }}
                                    </td>
                                    <td class="text-end small">
                                        @if (! $line->isIncrease())
                                            <span class="text-secondary">average cost</span>
                                        @elseif ($line->unit_cost !== null)
                                            <x-money :amount="(string) $line->unit_cost" />
                                        @else
                                            <span class="text-secondary">average cost</span>
                                        @endif
                                    </td>
                                    @if ($adjustment->isPending())
                                        <td class="text-end">{{ $quantity::format($available->get($line->item_id, '0')) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Details</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Warehouse</dt><dd class="col-7">{{ $adjustment->warehouse->name }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Date</dt><dd class="col-7">{{ $settings->formatDate($adjustment->adjusted_at) }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Raised by</dt><dd class="col-7">{{ $adjustment->creator?->name ?? '—' }}</dd>
                        @if ($adjustment->approved_at)
                            <dt class="col-5 text-secondary fw-normal">Approved</dt><dd class="col-7">{{ $settings->formatDate($adjustment->approved_at) }} by {{ $adjustment->approver?->name }}</dd>
                        @endif
                    </dl>
                    <hr>
                    <div class="small text-secondary mb-1">Reason</div>
                    <p class="small mb-0" style="white-space: pre-line">{{ $adjustment->reason }}</p>
                </div>
            </div>

            <x-status-history :histories="$adjustment->statusHistories" :labels="collect(App\Enums\StockAdjustmentStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
        </div>
    </div>
</x-layouts::app>
