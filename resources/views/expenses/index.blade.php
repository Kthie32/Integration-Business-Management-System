@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Expenses">
    <x-page-header title="Expenses" subtitle="Money spent by the organization. Recorded expenses are voided, never deleted."
        :breadcrumbs="['Expenses' => null]">
        <x-slot:actions>
            @can('viewAny', App\Models\ExpenseCategory::class)
                <a href="{{ route('expense-categories.index') }}" class="btn btn-outline-secondary"><i class="bi bi-tags me-1" aria-hidden="true"></i>Categories</a>
            @endcan
            @can('create', App\Models\Expense::class)
                <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New expense</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4">
            <x-stat-card label="Recorded (matching filters)" :value="$settings->formatMoney($total)" icon="wallet" variant="danger"
                hint="Drafts and void expenses are excluded." />
        </div>
    </div>

    <x-filter-bar :action="route('expenses.index')" :reset-url="route('expenses.index')">
        <div class="col-md-3">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Number, description, payee, reference" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label for="category" class="form-label small">Category</label>
            <select name="category" id="category" class="form-select">
                <option value="">Any</option>
                @foreach ($categories as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['category'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="department" class="form-label small">Department</label>
            <select name="department" id="department" class="form-select">
                <option value="">Any</option>
                @foreach ($departments as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['department'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <label for="status" class="form-label small">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Any</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="from" class="form-label small">From</label>
            <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label for="to" class="form-label small">To</label>
            <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
        </div>
    </x-filter-bar>

    <x-data-table :empty="$expenses->isEmpty()" empty-message="No expenses found." empty-icon="wallet" :paginator="$expenses">
        <x-slot:head>
            <tr>
                <th scope="col">Expense</th>
                <th scope="col">Date</th>
                <th scope="col">Description</th>
                <th scope="col">Category</th>
                <th scope="col">Paid to</th>
                <th scope="col" class="text-end">Amount</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($expenses as $expense)
            <tr>
                <td class="fw-semibold text-nowrap"><a href="{{ route('expenses.show', $expense) }}" class="text-decoration-none">{{ $expense->displayNumber() }}</a></td>
                <td class="small text-nowrap">{{ $settings->formatDate($expense->expense_date) }}</td>
                <td class="small">{{ Str::limit($expense->description, 60) }}@if ($expense->department)<div class="text-secondary">{{ $expense->department->name }}</div>@endif</td>
                <td class="small">{{ $expense->category->name }}</td>
                <td class="small">{{ $expense->supplier?->name ?? $expense->payee_name ?? '—' }}</td>
                <td @class(['text-end', 'text-decoration-line-through text-secondary' => $expense->status === App\Enums\ExpenseStatus::Void])><x-money :amount="(string) $expense->amount" /></td>
                <td><x-status-badge :status="$expense->status" /></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
