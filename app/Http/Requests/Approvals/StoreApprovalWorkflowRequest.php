<?php

namespace App\Http\Requests\Approvals;

use App\Enums\ApprovableDocument;
use App\Enums\ApproverType;
use App\Models\ApprovalWorkflow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or editing an approval workflow and its ordered steps (FR-REQ-03).
 */
class StoreApprovalWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workflow = $this->route('approval_workflow');

        return $workflow instanceof ApprovalWorkflow
            ? $this->user()->can('update', $workflow)
            : $this->user()->can('create', ApprovalWorkflow::class);
    }

    /**
     * Drop empty step rows the form may send.
     */
    protected function prepareForValidation(): void
    {
        $steps = collect((array) $this->input('steps', []))
            ->filter(fn ($step): bool => is_array($step) && filled($step['approver_type'] ?? null))
            ->values()
            ->all();

        $this->merge(['steps' => $steps, 'is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::enum(ApprovableDocument::class)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'is_active' => ['boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.name' => ['nullable', 'string', 'max:100'],
            'steps.*.approver_type' => ['required', Rule::in(array_keys(ApproverType::configurableOptions()))],
            'steps.*.role_id' => ['nullable', 'required_if:steps.*.approver_type,'.ApproverType::Role->value, 'integer', Rule::exists('roles', 'id')],
            'steps.*.user_id' => ['nullable', 'required_if:steps.*.approver_type,'.ApproverType::User->value, 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'steps.*.min_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'steps.required' => 'Add at least one step.',
            'steps.*.role_id.required_if' => 'Choose the role for this step.',
            'steps.*.user_id.required_if' => 'Choose the user for this step.',
        ];
    }
}
