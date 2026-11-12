<?php

namespace Database\Factories;

use App\Enums\ApprovableDocument;
use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active requisition workflow with no steps; add steps with ->steps()->create().
 *
 * @extends Factory<ApprovalWorkflow>
 */
class ApprovalWorkflowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => ApprovableDocument::Requisition,
            'name' => fake()->unique()->words(3, true),
            'min_amount' => null,
            'is_active' => true,
        ];
    }
}
