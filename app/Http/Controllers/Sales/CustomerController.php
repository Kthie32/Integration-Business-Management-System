<?php

namespace App\Http\Controllers\Sales;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCustomerRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Services\Parties\CustomerService;
use App\Services\System\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $customers) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:'.implode(',', array_column(CustomerType::cases(), 'value'))],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $customers = Customer::query()
            ->search($filters['search'] ?? null)
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', ['customers' => $customers, 'filters' => $filters]);
    }

    public function create(SettingsService $settings): View
    {
        Gate::authorize('create', Customer::class);

        return view('customers.create', ['customer' => new Customer([
            'type' => CustomerType::Company,
            'payment_terms_days' => (int) $settings->get('finance', 'invoice_payment_terms_days', 30),
            'is_active' => true,
        ])]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers->create($request->validated(), $request->user());

        return redirect()->route('customers.show', $customer)->with('success', "Customer {$customer->code} created.");
    }

    public function show(Request $request, Customer $customer): View
    {
        Gate::authorize('view', $customer);

        return view('customers.show', [
            'customer' => $customer,
            'quotations' => $request->user()->can('viewAny', Quotation::class)
                ? $customer->quotations()->latest('quote_date')->latest('id')->limit(10)->get()
                : null,
            'invoices' => $request->user()->can('viewAny', Invoice::class)
                ? $customer->invoices()->latest('invoice_date')->latest('id')->limit(10)->get()
                : null,
            'outstanding' => $request->user()->can('viewAny', Invoice::class) ? $customer->outstandingBalance() : null,
            'payments' => $request->user()->can('viewAny', Payment::class)
                ? $customer->payments()->with('invoice')->latest('payment_date')->latest('id')->limit(10)->get()
                : null,
            'activity' => $request->user()->can('audit.view')
                ? AuditLog::query()->forModel($customer)->with('user')->latest('id')->limit(10)->get()
                : collect(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('customers.edit', ['customer' => $customer]);
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $this->customers->delete($customer);

        return redirect()->route('customers.index')->with('success', "Customer {$customer->code} deleted.");
    }
}
