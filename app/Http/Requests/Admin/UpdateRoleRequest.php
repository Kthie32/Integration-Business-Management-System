<?php

namespace App\Http\Requests\Admin;

/**
 * Same rules as creating a role; the unique name rule ignores the role being edited.
 */
class UpdateRoleRequest extends StoreRoleRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }
}
