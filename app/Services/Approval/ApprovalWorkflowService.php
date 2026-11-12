<?php

namespace App\Services\Approval;

use App\Enums\ApproverType;
use App\Models\ApprovalWorkflow;
use Illuminate\Support\Facades\DB;

/**
 * Saves approval workflow templates. Requests already in approval keep the steps copied when they were
 * submitted, so editing or deleting a workflow is always safe.
 */
class ApprovalWorkflowService
{
    /**
     * @param  array{document_type: string, name: string, description?: string|null, min_amount?: string|null, is_active?: bool, steps: list<array{name?: string|null, approver_type: string, role_id?: int|string|null, user_id?: int|string|null, min_amount?: string|null}>}  $data
     */
    public function save(ApprovalWorkflow $workflow, array $data): ApprovalWorkflow
    {
        return DB::transaction(function () use ($workflow, $data): ApprovalWorkflow {
            $workflow->fill([
                'document_type' => $data['document_type'],
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'min_amount' => filled($data['min_amount'] ?? null) ? $data['min_amount'] : null,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ])->save();

            $workflow->steps()->delete();

            foreach (array_values($data['steps']) as $index => $step) {
                $type = ApproverType::from($step['approver_type']);

                $workflow->steps()->create([
                    'sequence' => $index + 1,
                    'name' => filled($step['name'] ?? null) ? trim((string) $step['name']) : null,
                    'approver_type' => $type,
                    'role_id' => $type === ApproverType::Role ? (int) $step['role_id'] : null,
                    'user_id' => $type === ApproverType::User ? (int) $step['user_id'] : null,
                    'min_amount' => filled($step['min_amount'] ?? null) ? $step['min_amount'] : null,
                ]);
            }

            return $workflow->load('steps');
        });
    }

    public function delete(ApprovalWorkflow $workflow): void
    {
        $workflow->delete();
    }
}
