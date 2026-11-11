<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Services\Finance\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ExpenseCategory::class);

        return view('expenses.categories.index', [
            'categories' => ExpenseCategory::query()->withCount('expenses')->orderBy('name')->get(),
            'category' => new ExpenseCategory(['is_active' => true]),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $category = $this->expenses->saveCategory(new ExpenseCategory, $request->validated());

        return redirect()->route('expense-categories.index')->with('success', "Category \"{$category->name}\" created.");
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        Gate::authorize('update', $expenseCategory);

        return view('expenses.categories.edit', ['category' => $expenseCategory]);
    }

    public function update(StoreExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->expenses->saveCategory($expenseCategory, $request->validated());

        return redirect()->route('expense-categories.index')->with('success', 'Category updated.');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        Gate::authorize('delete', $expenseCategory);

        $this->expenses->deleteCategory($expenseCategory);

        return redirect()->route('expense-categories.index')->with('success', "Category \"{$expenseCategory->name}\" deleted.");
    }
}
