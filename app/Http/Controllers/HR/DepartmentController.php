<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreDepartmentRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Services\HR\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(private DepartmentService $departments) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Department::class);

        return view('departments.index', [
            'departments' => Department::query()
                ->with(['parent', 'head'])
                ->withCount(['employees' => fn ($query) => $query->current()])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Department::class);

        return view('departments.create', [
            'department' => new Department(['is_active' => true]),
            'parents' => $this->parentOptions(),
            'heads' => $this->headOptions(),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = $this->departments->create($request->validated());

        return redirect()->route('departments.index')->with('success', "Department \"{$department->name}\" created.");
    }

    public function edit(Department $department): View
    {
        Gate::authorize('update', $department);

        return view('departments.edit', [
            'department' => $department,
            'parents' => $this->parentOptions($department),
            'heads' => $this->headOptions(),
        ]);
    }

    public function update(StoreDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->departments->update($department, $request->validated());

        return redirect()->route('departments.index')->with('success', "Department \"{$department->name}\" updated.");
    }

    public function destroy(Department $department): RedirectResponse
    {
        Gate::authorize('delete', $department);

        $this->departments->delete($department);

        return redirect()->route('departments.index')->with('success', "Department \"{$department->name}\" deleted.");
    }

    /**
     * @return Collection<int|string, string>
     */
    private function parentOptions(?Department $except = null): Collection
    {
        return Department::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * @return Collection<int|string, string>
     */
    private function headOptions(): Collection
    {
        return Employee::query()
            ->current()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => "{$employee->fullName()} ({$employee->employee_number})"]);
    }
}
