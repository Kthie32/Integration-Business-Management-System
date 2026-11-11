<?php

namespace App\Http\Controllers\Sales;

use App\Enums\DiscountType;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreQuotationRequest;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\TaxRate;
use App\Services\Sales\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(private QuotationService $quotations) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Quotation::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(QuotationStatus::options()))],
            'customer' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'in:quote_date,valid_until,total,number'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $quotations = Quotation::query()
            ->with('customer')
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['customer'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('quote_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('quote_date', '<=', $to))
            ->orderBy($filters['sort'] ?? 'quote_date', $filters['direction'] ?? 'desc')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('quotations.index', [
            'quotations' => $quotations,
            'filters' => $filters,
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => QuotationStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Quotation::class);

        return view('quotations.create', [
            'quotation' => new Quotation([
                'customer_id' => $request->integer('customer') ?: null,
                'quote_date' => today(),
                'valid_until' => today()->addDays($this->quotations->validityDays()),
            ]),
            'lines' => [],
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        $quotation = $this->quotations->createDraft($request->quotationData(), $request->user());

        if ($request->boolean('submit_now') && $request->user()->can('submit', $quotation)) {
            return $this->submitted($this->quotations->submit($quotation, $request->user()));
        }

        return redirect()->route('quotations.show', $quotation)->with('success', 'Draft saved.');
    }

    public function show(Quotation $quotation): View
    {
        Gate::authorize('view', $quotation);

        return view('quotations.show', [
            'quotation' => $quotation->load(
                'customer', 'items.item', 'creator', 'approver', 'converter', 'parent', 'revisionChild', 'statusHistories.user',
            ),
            'requiresApproval' => $quotation->isDraft() && $this->quotations->requiresApproval($quotation),
        ]);
    }

    public function edit(Quotation $quotation): View
    {
        Gate::authorize('update', $quotation);

        return view('quotations.edit', [
            'quotation' => $quotation,
            'lines' => $quotation->items->map(fn ($line): array => [
                'item_id' => $line->item_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'discount_amount' => (string) $line->discount_amount,
                'tax_rate_id' => $line->tax_rate_id,
            ])->all(),
            ...$this->formOptions(),
        ]);
    }

    public function update(StoreQuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $quotation = $this->quotations->updateDraft($quotation, $request->quotationData());

        if ($request->boolean('submit_now') && $request->user()->can('submit', $quotation)) {
            return $this->submitted($this->quotations->submit($quotation, $request->user()));
        }

        return redirect()->route('quotations.show', $quotation)->with('success', 'Draft updated.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('delete', $quotation);

        $this->quotations->deleteDraft($quotation);

        return redirect()->route('quotations.index')->with('success', 'Draft deleted.');
    }

    private function submitted(Quotation $quotation): RedirectResponse
    {
        $message = $quotation->status === QuotationStatus::PendingApproval
            ? "{$quotation->number} was submitted for approval."
            : "{$quotation->number} is ready to send to the customer.";

        return redirect()->route('quotations.show', $quotation)->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'items' => Item::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit', 'selling_price', 'tax_rate_id', 'type']),
            'taxRates' => TaxRate::query()->active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'rate', 'is_default']),
            'discountTypes' => collect(DiscountType::cases())->mapWithKeys(fn (DiscountType $type): array => [$type->value => $type->label()]),
        ];
    }
}
