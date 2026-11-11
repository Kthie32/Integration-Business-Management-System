@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app :title="$customer->name">
    <x-page-header :title="$customer->name" :subtitle="$customer->code.' · '.$customer->type->label()"
        :breadcrumbs="['Customers' => route('customers.index'), $customer->name => null]">
        <x-slot:actions>
            @can('update', $customer)
                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
            @can('delete', $customer)
                <form method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="Delete {{ $customer->name }}? This cannot be undone.">
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
                        @if ($customer->is_active)
                            <x-status-badge label="Active" color="success" />
                        @else
                            <x-status-badge label="Inactive" color="secondary" />
                        @endif
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-5 text-secondary fw-normal">TIN</dt><dd class="col-sm-7">{{ $customer->tin ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Contact person</dt><dd class="col-sm-7">{{ $customer->contact_person ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Phone</dt><dd class="col-sm-7">{{ $customer->phone ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Email</dt><dd class="col-sm-7">{{ $customer->email ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Address</dt><dd class="col-sm-7">{{ collect([$customer->address, $customer->city])->filter()->implode(', ') ?: '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Payment terms</dt><dd class="col-sm-7">{{ $customer->payment_terms_days }} days</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Credit limit</dt>
                        <dd class="col-sm-7">@if ($customer->credit_limit !== null)<x-money :amount="$customer->credit_limit" />@else No limit @endif</dd>
                    </dl>
                    @if ($customer->notes)
                        <hr>
                        <p class="small mb-0" style="white-space: pre-line">{{ $customer->notes }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Quotations
                    @can('create', App\Models\Quotation::class)
                        @if ($customer->is_active)
                            <a href="{{ route('quotations.create', ['customer' => $customer->id]) }}" class="btn btn-sm btn-outline-primary ms-auto"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New quotation</a>
                        @endif
                    @endcan
                </div>
                @if ($quotations === null)
                    <div class="card-body text-secondary small">You do not have access to quotations.</div>
                @elseif ($quotations->isEmpty())
                    <div class="card-body text-center text-secondary py-5">
                        <i class="bi bi-file-earmark-text fs-2 d-block mb-2" aria-hidden="true"></i>
                        No quotations yet. Invoices and payments will appear here once those modules are live.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th scope="col">Quotation</th><th scope="col">Date</th><th scope="col" class="text-end">Total</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                                @foreach ($quotations as $quotation)
                                    <tr>
                                        <td><a href="{{ route('quotations.show', $quotation) }}" class="text-decoration-none">{{ $quotation->displayNumber() }}</a></td>
                                        <td class="small">{{ $settings->formatDate($quotation->quote_date) }}</td>
                                        <td class="text-end"><x-money :amount="(string) $quotation->total" /></td>
                                        <td><x-status-badge :status="$quotation->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-transparent small"><a href="{{ route('quotations.index', ['customer' => $customer->id]) }}">All quotations for this customer</a></div>
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
