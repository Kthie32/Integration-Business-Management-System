@inject('settings', App\Services\System\SettingsService::class)
@php($quantity = App\Support\Quantity::class)
@php($showDelivery = $requisition->approved_at !== null)
<x-layouts::app :title="'Requisition '.$requisition->displayNumber()">
    <x-page-header :title="'Requisition '.$requisition->displayNumber()" :subtitle="$requisition->department?->name"
        :breadcrumbs="['Requisitions' => route('requisitions.index'), $requisition->displayNumber() => null]">
        <x-slot:actions>
            @can('submit', $requisition)
                <form method="POST" action="{{ route('requisitions.submit', $requisition) }}" data-confirm="Submit {{ $requisition->displayNumber() }} for approval?">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1" aria-hidden="true"></i>Submit for approval</button>
                </form>
            @endcan
            @can('update', $requisition)
                <a href="{{ route('requisitions.edit', $requisition) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
            @can('fulfil', $requisition)
                <a href="{{ route('requisitions.fulfil', $requisition) }}" class="btn btn-primary"><i class="bi bi-box-arrow-up me-1" aria-hidden="true"></i>Fulfil</a>
            @endcan
            @if ($requisition->number)
                <a href="{{ route('requisitions.print', $requisition) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</a>
                <a href="{{ route('requisitions.pdf', $requisition) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF</a>
            @endif
            @can('cancel', $requisition)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancelForm" aria-expanded="false" aria-controls="cancelForm">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancel request
                </button>
            @endcan
            @can('delete', $requisition)
                <form method="POST" action="{{ route('requisitions.destroy', $requisition) }}" data-confirm="Delete this draft requisition?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @can('cancel', $requisition)
        <div @class(['collapse mb-4', 'show' => $errors->has('reason')]) id="cancelForm">
            <div class="card card-body border-danger-subtle">
                <form method="POST" action="{{ route('requisitions.cancel', $requisition) }}" class="row g-2 align-items-end" novalidate>
                    @csrf
                    <div class="col-md">
                        <x-form.input name="reason" label="Why are you cancelling?" maxlength="500" required help="Cancelling stops the approval and cannot be undone." />
                    </div>
                    <div class="col-md-auto mb-3">
                        <button type="submit" class="btn btn-danger">Cancel requisition</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @if ($requisition->status->isAwaitingApproval())
        @can('decide', $requisition)
            <div class="card border-warning-subtle mb-4">
                <div class="card-body">
                    <h2 class="h6">Your decision — {{ $pendingStep?->name }}</h2>
                    <p class="small text-secondary">Approving passes the request to the next step, or completes the approval if this is the last one. Rejecting ends it; returning sends it back to the requester to revise.</p>
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <form method="POST" action="{{ route('requisitions.approve', $requisition) }}" data-confirm="Approve {{ $requisition->displayNumber() }}?" novalidate>
                                @csrf
                                <label for="approve_comment" class="form-label small">Comment (optional)</label>
                                <input type="text" name="comment" id="approve_comment" maxlength="1000" class="form-control mb-2">
                                <button type="submit" class="btn btn-success"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Approve</button>
                            </form>
                        </div>
                        <div class="col-lg-4">
                            <form method="POST" action="{{ route('requisitions.return', $requisition) }}" novalidate>
                                @csrf
                                <label for="return_comment" class="form-label small">What should change? <span class="text-danger" aria-hidden="true">*</span></label>
                                <input type="text" name="comment" id="return_comment" maxlength="1000" class="form-control mb-2">
                                <button type="submit" class="btn btn-outline-info"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>Return for revision</button>
                            </form>
                        </div>
                        <div class="col-lg-4">
                            <form method="POST" action="{{ route('requisitions.reject', $requisition) }}" novalidate>
                                @csrf
                                <label for="reject_comment" class="form-label small">Reason for rejecting <span class="text-danger" aria-hidden="true">*</span></label>
                                <input type="text" name="comment" id="reject_comment" maxlength="1000" class="form-control mb-2">
                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x me-1" aria-hidden="true"></i>Reject</button>
                            </form>
                        </div>
                    </div>
                    @error('comment')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>
            </div>
        @else
            <div class="alert alert-info small">
                <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                Waiting for <strong>{{ $pendingStep?->name ?? 'approval' }}</strong>@if ($waitingFor->isNotEmpty()) ({{ $waitingFor->implode(', ') }})@endif.
            </div>
        @endcan
    @elseif ($requisition->status === App\Enums\RequisitionStatus::Returned && $requisition->requested_by === auth()->id())
        <div class="alert alert-info small">
            <i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>This requisition was returned to you for revision. Edit it and submit it again.
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Lines
                    <span class="ms-auto"><x-status-badge :status="$requisition->status" /></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Description</th>
                                <th scope="col" class="text-end">Requested</th>
                                @if ($showDelivery)
                                    <th scope="col" class="text-end">Approved</th>
                                    <th scope="col" class="text-end">Delivered</th>
                                @endif
                                <th scope="col" class="text-end">Est. unit cost</th>
                                <th scope="col" class="text-end">Est. total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requisition->items as $line)
                                <tr>
                                    <td>
                                        {{ $line->description }}
                                        @if ($line->item)<div class="small text-secondary"><code>{{ $line->item->code }}</code> · {{ $line->item->isStocked() ? 'stock item' : 'service' }}</div>@endif
                                    </td>
                                    <td class="text-end text-nowrap">{{ $quantity::format($line->quantity) }} {{ $line->unit }}</td>
                                    @if ($showDelivery)
                                        <td class="text-end">{{ $quantity::format($line->approved_quantity) }}</td>
                                        <td class="text-end">{{ $quantity::format($line->fulfilled_quantity) }}</td>
                                    @endif
                                    <td class="text-end"><x-money :amount="(string) $line->estimated_unit_cost" /></td>
                                    <td class="text-end"><x-money :amount="(string) $line->estimated_total" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="{{ $showDelivery ? 5 : 3 }}" class="text-end">Estimated total</th>
                                <th class="text-end"><x-money :amount="(string) $requisition->estimated_total" /></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Approval trail</div>
                @if ($requisition->approvals->isEmpty())
                    <div class="card-body small text-secondary">Not submitted yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Round</th>
                                    <th scope="col">Step</th>
                                    <th scope="col">Approver</th>
                                    <th scope="col">Decision</th>
                                    <th scope="col">When</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requisition->approvals as $approval)
                                    <tr>
                                        <td>{{ $approval->round }}</td>
                                        <td>{{ $approval->sequence }}. {{ $approval->name }}<div class="text-secondary">{{ $approval->approverLabel() }}</div></td>
                                        <td>{{ $approval->decider?->name ?? '—' }}</td>
                                        <td>
                                            <x-status-badge :label="$approval->status->label()" :color="$approval->status->color()" />
                                            @if ($approval->comment)<div class="mt-1">{{ $approval->comment }}</div>@endif
                                        </td>
                                        <td class="text-nowrap">{{ $approval->decided_at ? $settings->formatDate($approval->decided_at).' '.$approval->decided_at->format('H:i') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if ($requisition->fulfilments->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Deliveries</div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Line</th>
                                    <th scope="col">How</th>
                                    <th scope="col" class="text-end">Quantity</th>
                                    <th scope="col" class="text-end">Cost</th>
                                    <th scope="col">By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requisition->fulfilments as $fulfilment)
                                    <tr>
                                        <td class="text-nowrap">{{ $settings->formatDate($fulfilment->fulfilled_at) }}</td>
                                        <td>{{ $fulfilment->requisitionItem?->description }}</td>
                                        <td>
                                            {{ $fulfilment->fulfilment_type->label() }}
                                            @if ($fulfilment->warehouse)<div class="text-secondary">{{ $fulfilment->warehouse->name }}</div>@endif
                                            @if ($fulfilment->expense)
                                                <div>
                                                    @can('view', $fulfilment->expense)
                                                        <a href="{{ route('expenses.show', $fulfilment->expense) }}">Expense {{ $fulfilment->expense->displayNumber() }}</a>
                                                    @else
                                                        Expense {{ $fulfilment->expense->displayNumber() }}
                                                    @endcan
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $quantity::format($fulfilment->quantity) }}</td>
                                        <td class="text-end">@if ($fulfilment->amount !== null)<x-money :amount="(string) $fulfilment->amount" />@else — @endif</td>
                                        <td>{{ $fulfilment->fulfiller?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Details</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Requested by</dt><dd class="col-7">{{ $requisition->requester?->name ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Department</dt><dd class="col-7">{{ $requisition->department?->name }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Priority</dt><dd class="col-7"><x-status-badge :label="$requisition->priority->label()" :color="$requisition->priority->color()" /></dd>
                        <dt class="col-5 text-secondary fw-normal">Needed by</dt><dd class="col-7">{{ $requisition->required_by_date ? $settings->formatDate($requisition->required_by_date) : '—' }}</dd>
                        @if ($requisition->submitted_at)
                            <dt class="col-5 text-secondary fw-normal">Submitted</dt><dd class="col-7">{{ $settings->formatDate($requisition->submitted_at) }}</dd>
                        @endif
                        @if ($requisition->approved_at)
                            <dt class="col-5 text-secondary fw-normal">Approved</dt><dd class="col-7">{{ $settings->formatDate($requisition->approved_at) }}</dd>
                        @endif
                        <dt class="col-5 text-secondary fw-normal">Attachment</dt>
                        <dd class="col-7">
                            @if ($requisition->hasAttachment())
                                <a href="{{ route('requisitions.attachment', $requisition) }}"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ $requisition->attachment_name }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </dl>
                    <hr>
                    <div class="small text-secondary mb-1">Purpose</div>
                    <p class="small mb-0" style="white-space: pre-line">{{ $requisition->purpose }}</p>
                </div>
            </div>

            <x-status-history :histories="$requisition->statusHistories" :labels="App\Enums\RequisitionStatus::options()" />
        </div>
    </div>
</x-layouts::app>
