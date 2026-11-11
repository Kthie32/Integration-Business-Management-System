<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Sales\CustomerStatementService;
use App\Services\System\AuditLogger;
use App\Services\System\DocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A customer's statement of account for a date range, on screen, printable and as a PDF (FR-PAY-06).
 * The range defaults to the start of the current year up to today.
 */
class CustomerStatementController extends Controller
{
    public function __construct(
        private CustomerStatementService $statements,
        private DocumentBranding $branding,
        private AuditLogger $auditLogger,
    ) {}

    public function show(Request $request, Customer $customer): View
    {
        Gate::authorize('viewStatement', $customer);

        return view('customers.statement', ['statement' => $this->statement($request, $customer)]);
    }

    public function pdf(Request $request, Customer $customer): Response
    {
        Gate::authorize('viewStatement', $customer);

        $statement = $this->statement($request, $customer);
        $this->auditLogger->log('pdf_downloaded', $customer, "Downloaded statement {$statement['from']->toDateString()} to {$statement['to']->toDateString()}");

        return Pdf::loadView('pdf.statement', ['statement' => $statement, 'organization' => $this->branding->details(), 'forPdf' => true])
            ->setPaper('a4')
            ->download('Statement-'.$customer->code.'-'.$statement['to']->toDateString().'.pdf');
    }

    public function print(Request $request, Customer $customer): View
    {
        Gate::authorize('viewStatement', $customer);

        return view('pdf.statement', [
            'statement' => $this->statement($request, $customer),
            'organization' => $this->branding->details(),
            'forPdf' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statement(Request $request, Customer $customer): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($data['to']) ? Carbon::parse($data['to']) : today();
        $from = isset($data['from']) ? Carbon::parse($data['from']) : $to->copy()->startOfYear();

        return $this->statements->build($customer, $from, $to);
    }
}
