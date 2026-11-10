<?php

namespace App\Http\Requests\Admin;

/**
 * Same rules as creating a user; the unique email rule ignores the user being edited.
 */
class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }
}
