<?php

namespace App\Services\HR;

use App\Exceptions\BusinessRuleException;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates, edits and removes departments, keeping the department tree free of cycles.
 */
class DepartmentService
{
    /**
     * @param  array{code: string, name: string, parent_id?: int|null, head_employee_id?: int|null, description?: string|null, is_active?: bool}  $data
     */
    public function create(array $data): Department
    {
        return Department::query()->create($this->attributes($data));
    }

    /**
     * @param  array{code: string, name: string, parent_id?: int|null, head_employee_id?: int|null, description?: string|null, is_active?: bool}  $data
     */
    public function update(Department $department, array $data): Department
    {
        $attributes = $this->attributes($data);

        if ($attributes['parent_id'] !== null) {
            $parent = Department::query()->findOrFail($attributes['parent_id']);

            if ($parent->is($department) || in_array($department->id, $parent->ancestorIds(), true)) {
                throw new BusinessRuleException('A department cannot be placed under itself or one of its own sub-departments.');
            }
        }

        if (! $attributes['is_active'] && $department->is_active && $department->employees()->current()->exists()) {
            throw new BusinessRuleException('Move this department\'s current employees to another department before deactivating it.');
        }

        $department->update($attributes);

        return $department;
    }

    /**
     * Departments that have employees or sub-departments are kept for history; deactivate them instead.
     */
    public function delete(Department $department): void
    {
        DB::transaction(function () use ($department): void {
            if ($department->employees()->withTrashed()->exists()) {
                throw new BusinessRuleException('This department has employees and cannot be deleted. Deactivate it instead.');
            }

            if ($department->children()->exists()) {
                throw new BusinessRuleException('This department has sub-departments. Move or delete them first.');
            }

            $department->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{code: string, name: string, parent_id: int|null, head_employee_id: int|null, description: string|null, is_active: bool}
     */
    private function attributes(array $data): array
    {
        return [
            'code' => Str::upper($data['code']),
            'name' => $data['name'],
            'parent_id' => isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            'head_employee_id' => isset($data['head_employee_id']) ? (int) $data['head_employee_id'] : null,
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
