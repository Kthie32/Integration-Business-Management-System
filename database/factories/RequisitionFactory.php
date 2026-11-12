<?php

namespace Database\Factories;

use App\Enums\RequisitionPriority;
use App\Enums\RequisitionStatus;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft requisition with no lines. Create real requisitions through the RequisitionService.
 *
 * @extends Factory<Requisition>
 */
class RequisitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'requested_by' => User::factory(),
            'priority' => RequisitionPriority::Normal,
            'purpose' => fake()->sentence(6),
            'estimated_total' => '0.00',
            'status' => RequisitionStatus::Draft,
        ];
    }
}
