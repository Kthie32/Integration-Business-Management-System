<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Receipt;
use App\Services\Sales\PaymentService;
use App\Services\System\AuditLogger;
use App\Services\System\DocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The receipt register, a receipt's detail page, its printable/PDF copy, and reversal from the receipt
 * (which reverses the underlying payment).
 */
class ReceiptController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private DocumentBranding $branding,
        private AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Receipt::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(ReceiptStatus::options()))],
            'customer' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Receipt::query()
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['customer'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('receipt_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('receipt_date', '<=', $to));

        return view('receipts.index', [
            'receipts' => (clone $query)->with('invoice', 'customer', 'payment')->latest('receipt_date')->latest('id')->paginate(25)->withQueryString(),
            'total' => (string) ((clone $query)->valid()->sum('amount') ?: '0'),
            'filters' => $filters,
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => ReceiptStatus::options(),
        ]);
    }

    public function show(Receipt $receipt): View
    {
        Gate::authorize('view', $receipt);

        return view('receipts.show', [
            'receipt' => $receipt->load('payment.recorder', 'payment.reverser', 'invoice', 'customer', 'issuer', 'voider', 'statusHistories.user'),
        ]);
    }

    public function pdf(Receipt $receipt): Response
    {
        Gate::authorize('view', $receipt);

        $this->auditLogger->log('pdf_downloaded', $receipt, 'Downloaded receipt PDF');

        return Pdf::loadView('pdf.receipt', $this->documentData($receipt, forPdf: true))
            ->setPaper('a5')
            ->download(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $receipt->number), '-').'.pdf');
    }

    public function print(Receipt $receipt): View
    {
        Gate::authorize('view', $receipt);

        return view('pdf.receipt', $this->documentData($receipt, forPdf: false));
    }

    public function reverse(Request $request, Receipt $receipt): RedirectResponse
    {
        Gate::authorize('reverse', $receipt);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $payment = $this->payments->reverse($receipt->payment, $request->user(), $data['reason']);

        return redirect()->route('receipts.show', $receipt)
            ->with('success', "Receipt {$receipt->number} voided and payment {$payment->number} reversed. The invoice balance was restored.");
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Receipt $receipt, bool $forPdf): array
    {
        return [
            'receipt' => $receipt->load('payment', 'invoice', 'customer', 'issuer'),
            'organization' => $this->branding->details(),
            'forPdf' => $forPdf,
        ];
    }
}
