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
                <div class="card-header fw-semibold">Quotations, invoices and payments</div>
                <div class="card-body text-center text-secondary py-5">
                    <i class="bi bi-receipt fs-2 d-block mb-2" aria-hidden="true"></i>
                    This customer's sales history and outstanding balance will appear here once the sales modules are live.
                </div>
            </div>
        </div>

        @if ($activity->isNotEmpty())
            <div class="col-12">
                @include('partials.recent-activity', ['activity' => $activity])
            </div>
        @endif
    </div>
</x-layouts::app>
