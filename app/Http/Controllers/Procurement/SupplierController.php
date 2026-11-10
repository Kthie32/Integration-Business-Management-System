<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreSupplierRequest;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Services\Parties\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $suppliers) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Supplier::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $suppliers = Supplier::query()
            ->search($filters['search'] ?? null)
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('suppliers.index', ['suppliers' => $suppliers, 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('suppliers.create', ['supplier' => new Supplier(['payment_terms_days' => 30, 'is_active' => true])]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = $this->suppliers->create($request->validated(), $request->user());

        return redirect()->route('suppliers.show', $supplier)->with('success', "Supplier {$supplier->code} created.");
    }

    public function show(Request $request, Supplier $supplier): View
    {
        Gate::authorize('view', $supplier);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'activity' => $request->user()->can('audit.view')
                ? AuditLog::query()->forModel($supplier)->with('user')->latest('id')->limit(10)->get()
                : collect(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('update', $supplier);

        return view('suppliers.edit', ['supplier' => $supplier]);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->suppliers->update($supplier, $request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('delete', $supplier);

        $this->suppliers->delete($supplier);

        return redirect()->route('suppliers.index')->with('success', "Supplier {$supplier->code} deleted.");
    }
}
