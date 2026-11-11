<?php

namespace App\Http\Controllers\Sales;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\TaxRate;
use App\Models\Warehouse;
use App\Services\Sales\InvoiceService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:overdue,'.implode(',', array_keys(InvoiceStatus::options()))],
            'customer' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'in:invoice_date,due_date,total,balance_due,number'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $status = $filters['status'] ?? null;

        $invoices = Invoice::query()
            ->with('customer')
            ->search($filters['search'] ?? null)
            ->when($status === 'overdue', fn ($query) => $query->overdue())
            ->when($status !== null && $status !== 'overdue', fn ($query) => $query->where('status', $status))
            ->when($filters['customer'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('invoice_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('invoice_date', '<=', $to))
            ->orderBy($filters['sort'] ?? 'invoice_date', $filters['direction'] ?? 'desc')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => [...InvoiceStatus::options(), 'overdue' => 'Overdue'],
            'summary' => [
                'outstanding' => (string) (Invoice::query()->open()->sum('balance_due') ?: '0'),
                'overdue' => (string) (Invoice::query()->overdue()->sum('balance_due') ?: '0'),
                'overdueCount' => Invoice::query()->overdue()->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Invoice::class);

        $customer = $request->integer('customer') ? Customer::query()->find($request->integer('customer')) : null;

        return view('invoices.create', [
            'invoice' => new Invoice([
                'customer_id' => $customer?->id,
                'warehouse_id' => Warehouse::default()?->id,
                'invoice_date' => today(),
                'due_date' => $customer !== null ? $this->invoices->defaultDueDate($customer, today()) : null,
            ]),
            'lines' => [],
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->invoices->createDraft($request->invoiceData(), $request->user());

        if ($request->boolean('issue_now') && $request->user()->can('issue', $invoice)) {
            $invoice = $this->invoices->issue($invoice, $request->user());

            return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->number} issued.");
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'Draft saved.');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(
            'customer', 'warehouse', 'items.item', 'creator', 'issuer', 'voider', 'quotation', 'copiedFrom', 'corrections', 'statusHistories.user',
        );

        return view('invoices.show', [
            'invoice' => $invoice,
            'payments' => $request->user()->can('payments.view')
                ? $invoice->payments()->with('recorder', 'reverser')->get()
                : null,
            'stockMovements' => $request->user()->can('stock.view')
                ? $invoice->stockMovements()->with('item', 'warehouse')->get()
                : null,
            'paymentMethods' => PaymentMethod::options(),
            'exceedsCreditLimit' => $this->exceedsCreditLimit($invoice),
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        Gate::authorize('update', $invoice);

        return view('invoices.edit', [
            'invoice' => $invoice,
            'lines' => InvoiceService::editableLines($invoice),
            ...$this->formOptions(),
        ]);
    }

    public function update(StoreInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $invoice = $this->invoices->updateDraft($invoice, $request->invoiceData());

        if ($request->boolean('issue_now') && $request->user()->can('issue', $invoice)) {
            $invoice = $this->invoices->issue($invoice, $request->user());

            return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->number} issued.");
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'Draft updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        $this->invoices->deleteDraft($invoice);

        return redirect()->route('invoices.index')->with('success', 'Draft deleted.');
    }

    /**
     * Whether issuing this draft would take the customer past their credit limit (a warning only).
     */
    private function exceedsCreditLimit(Invoice $invoice): bool
    {
        if (! $invoice->isDraft() || $invoice->customer->credit_limit === null) {
            return false;
        }

        return Money::of($invoice->customer->outstandingBalance())
            ->plus((string) $invoice->total)
            ->greaterThan((string) $invoice->customer->credit_limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'payment_terms_days']),
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'code']),
            'items' => Item::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit', 'selling_price', 'tax_rate_id', 'type']),
            'taxRates' => TaxRate::query()->active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'rate', 'is_default']),
            'discountTypes' => collect(DiscountType::cases())->mapWithKeys(fn (DiscountType $type): array => [$type->value => $type->label()]),
        ];
    }
}
