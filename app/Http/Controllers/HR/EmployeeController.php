<?php

namespace App\Http\Controllers\HR;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\User;
use App\Services\HR\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $employees) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
            'type' => ['nullable', Rule::enum(EmploymentType::class)],
        ]);

        $employees = Employee::query()
            ->visibleTo($request->user())
            ->with('department')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($inner) => $inner
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('employee_number', 'like', $like)
                    ->orWhere('job_title', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->when($filters['department'] ?? null, fn ($query, int $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('employment_type', $type))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Employee::class);

        $employee = new Employee(['is_resident_for_tax' => true, 'employment_type' => EmploymentType::Permanent]);

        return view('employees.create', [
            'employee' => $employee,
            'departments' => $this->departmentOptions(),
            'users' => $this->linkableUsers(),
            'showSensitive' => request()->user()->can('employees.view_salary'),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = $this->employees->create($request->employeeData(), $request->user());

        return redirect()->route('employees.show', $employee)->with('success', "Employee {$employee->employee_number} registered.");
    }

    public function show(Request $request, Employee $employee): View
    {
        Gate::authorize('view', $employee);

        $actor = $request->user();

        return view('employees.show', [
            'employee' => $employee->load(['department', 'user', 'statusHistories.user', 'employmentHistories.recorder']),
            'showSensitive' => $actor->can('viewSensitive', $employee),
            'documents' => $actor->can('viewDocuments', $employee) ? $employee->documents()->with('uploader')->get() : null,
            'nextStatuses' => collect(EmployeeStatus::cases())->filter(fn (EmployeeStatus $status): bool => $employee->status->canTransitionTo($status))->values(),
            'salaryStructures' => $actor->can('viewSalary', $employee)
                ? $employee->salaryStructures()->with('components.payComponent', 'creator')->get()
                : null,
            'payComponents' => $actor->can('manageSalary', $employee)
                ? PayComponent::query()->active()->orderBy('kind')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function edit(Request $request, Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return view('employees.edit', [
            'employee' => $employee->load('user'),
            'departments' => $this->departmentOptions($employee->department_id),
            'users' => $this->linkableUsers($employee),
            'showSensitive' => $request->user()->can('viewSensitive', $employee),
        ]);
    }

    public function update(StoreEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employees->update($employee, $request->employeeData(), $request->user());

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    /**
     * Active departments, plus the employee's current one even if it has since been deactivated.
     *
     * @return Collection<int|string, string>
     */
    private function departmentOptions(?int $currentDepartmentId = null): Collection
    {
        return Department::query()
            ->where(fn ($query) => $query->where('is_active', true)->when($currentDepartmentId, fn ($inner) => $inner->orWhere('id', $currentDepartmentId)))
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * User accounts not yet linked to another employee.
     *
     * @return Collection<int|string, string>
     */
    private function linkableUsers(?Employee $employee = null): Collection
    {
        return User::query()
            ->where(fn ($query) => $query->whereNull('employee_id')->when($employee, fn ($inner) => $inner->orWhere('employee_id', $employee->id)))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} ({$user->email})"]);
    }
}
