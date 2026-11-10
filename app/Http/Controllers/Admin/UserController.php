<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->when($filters['role'] ?? null, fn ($query, int $roleId) => $query->whereHas('roles', fn ($roles) => $roles->whereKey($roleId)))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', [
            'user' => new User,
            'roles' => $this->assignableRoles($request->user()),
            'selectedRoles' => [],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->userService->create($request->validated(), $request->user());

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Account created. A link to set a password was emailed to {$user->email}.");
    }

    public function show(Request $request, User $user): View
    {
        Gate::authorize('view', $user);

        $actor = $request->user();

        return view('admin.users.show', [
            'user' => $user->load('roles', 'creator'),
            'sessions' => $actor->can('manageSessions', $user)
                ? $this->userService->activeSessions($user, $request->session()->getId())
                : collect(),
            'activity' => $actor->can('audit.view')
                ? AuditLog::query()->forModel($user)->with('user')->latest('id')->limit(15)->get()
                : collect(),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->assignableRoles($request->user()),
            'selectedRoles' => $user->roles()->pluck('id')->all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated(), $request->user());

        return redirect()->route('admin.users.show', $user)->with('success', 'User updated.');
    }

    /**
     * Roles the acting user may assign. Only a Super Administrator can grant the Super Administrator role.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(User $actor): Collection
    {
        return Role::query()
            ->when(! $actor->isSuperAdministrator(), fn ($query) => $query->where('name', '!=', SystemRole::SuperAdministrator->value))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }
}
