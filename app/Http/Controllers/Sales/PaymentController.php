<?php

namespace App\Http\Controllers\Sales;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\RecordPaymentRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Sales\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The payments register, recording a payment against an invoice, and reversing one.
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(PaymentStatus::options()))],
            'method' => ['nullable', 'in:'.implode(',', array_keys(PaymentMethod::options()))],
            'customer' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Payment::query()
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['method'] ?? null, fn ($query, string $method) => $query->where('method', $method))
            ->when($filters['customer'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('payment_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('payment_date', '<=', $to));

        return view('payments.index', [
            'payments' => (clone $query)->with('invoice', 'customer')->latest('payment_date')->latest('id')->paginate(25)->withQueryString(),
            'collected' => (string) ((clone $query)->completed()->sum('amount') ?: '0'),
            'filters' => $filters,
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => PaymentStatus::options(),
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function store(RecordPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $payment = $this->payments->record($invoice, $request->paymentData(), $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', "Payment {$payment->number} recorded.");
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('reverse', $payment);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $payment = $this->payments->reverse($payment, $request->user(), $data['reason']);

        return redirect()->route('invoices.show', $payment->invoice_id)->with('success', "Payment {$payment->number} reversed. The invoice balance was restored.");
    }
}
