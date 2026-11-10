<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Role;
use App\Services\Users\RoleService;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private RoleService $roleService) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::query()
                ->withCount(['users', 'permissions'])
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get(),
            'permissionTotal' => count(PermissionCatalog::all()),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.create', [
            'role' => new Role,
            'groups' => PermissionCatalog::groups(),
            'selected' => [],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = $this->roleService->create($request->validated());

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" created.");
    }

    /**
     * Shows the role's permissions; read-only when the user may not edit it.
     */
    public function edit(Role $role): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.edit', [
            'role' => $role,
            'groups' => PermissionCatalog::groups(),
            'selected' => $role->isSuperAdministrator() ? PermissionCatalog::all() : $role->permissions()->pluck('name')->all(),
            'readOnly' => Gate::denies('update', $role),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roleService->update($role, $request->validated());

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->roleService->delete($role);

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" deleted.");
    }
}
