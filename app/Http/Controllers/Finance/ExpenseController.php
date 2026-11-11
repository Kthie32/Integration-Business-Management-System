<?php

namespace App\Http\Controllers\Finance;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Services\Finance\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expense register, draft editing, recording, voiding and attachment download.
 */
class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Expense::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(ExpenseStatus::options()))],
            'category' => ['nullable', 'integer'],
            'department' => ['nullable', 'integer'],
            'supplier' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Expense::query()
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, int $id) => $query->where('expense_category_id', $id))
            ->when($filters['department'] ?? null, fn ($query, int $id) => $query->where('department_id', $id))
            ->when($filters['supplier'] ?? null, fn ($query, int $id) => $query->where('supplier_id', $id))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('expense_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('expense_date', '<=', $to));

        return view('expenses.index', [
            'expenses' => (clone $query)->with('category', 'supplier', 'department')->latest('expense_date')->latest('id')->paginate(25)->withQueryString(),
            'total' => (string) ((clone $query)->recorded()->sum('amount') ?: '0'),
            'filters' => $filters,
            'categories' => ExpenseCategory::query()->orderBy('name')->pluck('name', 'id'),
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => ExpenseStatus::options(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Expense::class);

        $expense = new Expense;
        $expense->forceFill(['expense_date' => today(), 'tax_amount' => '0', 'payment_method' => PaymentMethod::Cash]);

        return view('expenses.create', ['expense' => $expense, ...$this->formOptions(null)]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenses->createDraft($request->expenseData(), $request->file('attachment'), $request->user());

        return $this->afterSave($request, $expense, 'Draft expense saved.');
    }

    public function show(Expense $expense): View
    {
        Gate::authorize('view', $expense);

        return view('expenses.show', [
            'expense' => $expense->load('category', 'supplier', 'department', 'creator', 'recorder', 'voider', 'statusHistories.user'),
        ]);
    }

    public function edit(Expense $expense): View
    {
        Gate::authorize('update', $expense);

        return view('expenses.edit', ['expense' => $expense, ...$this->formOptions($expense)]);
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense = $this->expenses->updateDraft($expense, $request->expenseData(), $request->file('attachment'), $request->boolean('remove_attachment'));

        return $this->afterSave($request, $expense, 'Draft expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $this->expenses->deleteDraft($expense);

        return redirect()->route('expenses.index')->with('success', 'Draft expense deleted.');
    }

    public function record(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('record', $expense);

        $expense = $this->expenses->record($expense, $request->user());

        return redirect()->route('expenses.show', $expense)->with('success', "Expense {$expense->number} recorded.");
    }

    public function void(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('void', $expense);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $expense = $this->expenses->void($expense, $request->user(), $data['reason']);

        return redirect()->route('expenses.show', $expense)->with('success', "Expense {$expense->number} voided.");
    }

    public function attachment(Expense $expense): StreamedResponse
    {
        Gate::authorize('view', $expense);

        $disk = Storage::disk(Expense::DISK);
        abort_unless($expense->hasAttachment() && $disk->exists($expense->attachment_path), 404);

        return $disk->download($expense->attachment_path, $expense->attachment_name, [
            'Content-Type' => $expense->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * "Save and record" saves the draft first, so a failed recording still keeps the user's input.
     */
    private function afterSave(StoreExpenseRequest $request, Expense $expense, string $message): RedirectResponse
    {
        if ($request->wantsToRecord()) {
            $expense = $this->expenses->record($expense, $request->user());
            $message = "Expense {$expense->number} recorded.";
        }

        return redirect()->route('expenses.show', $expense)->with('success', $message);
    }

    /**
     * Active choices, plus the expense's current inactive category/supplier/department so editing keeps them.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Expense $expense): array
    {
        return [
            'categories' => ExpenseCategory::query()
                ->where(fn ($query) => $query->where('is_active', true)->when($expense?->expense_category_id, fn ($query, int $id) => $query->orWhere('id', $id)))
                ->orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::query()
                ->where(fn ($query) => $query->where('is_active', true)->when($expense?->supplier_id, fn ($query, int $id) => $query->orWhere('id', $id)))
                ->orderBy('name')->pluck('name', 'id'),
            'departments' => Department::query()
                ->where(fn ($query) => $query->where('is_active', true)->when($expense?->department_id, fn ($query, int $id) => $query->orWhere('id', $id)))
                ->orderBy('name')->pluck('name', 'id'),
            'methods' => PaymentMethod::options(),
        ];
    }
}
