@php
    $stepRows = old('steps', $workflow->exists
        ? $workflow->steps->map(fn ($step) => [
            'name' => $step->name,
            'approver_type' => $step->approver_type->value,
            'role_id' => $step->role_id,
            'user_id' => $step->user_id,
            'min_amount' => $step->min_amount !== null ? (string) $step->min_amount : null,
        ])->all()
        : []);
    if (count($stepRows) === 0) {
        $stepRows = [['approver_type' => App\Enums\ApproverType::DepartmentHead->value]];
    }
@endphp
<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <x-form.input name="name" label="Name" :value="$workflow->name" maxlength="100" required />
            </div>
            <div class="col-md-3">
                <x-form.select name="document_type" label="Applies to" :options="$documentTypes" :value="$workflow->document_type" required />
            </div>
            <div class="col-md-3">
                <x-form.input name="min_amount" label="Use from amount" type="number" step="0.01" min="0" :value="$workflow->min_amount" help="Empty: any amount." />
            </div>
        </div>
        <x-form.input name="description" label="Description" :value="$workflow->description" maxlength="255" />
        <x-form.checkbox name="is_active" label="Active" :checked="(bool) old('is_active', $workflow->is_active)" switch />
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold">Steps, in order</div>
    <div class="card-body">
        @error('steps')
            <div class="alert alert-danger small py-2">{{ $message }}</div>
        @enderror
        <p class="small text-secondary">
            Each step is decided by one person. The requester never approves their own request, and nobody approves two steps of the same request.
            A step nobody can decide (e.g. the requester is the department head) is skipped automatically. Choose a role for role steps and a user for named-user steps.
        </p>
        <div data-line-editor>
            <div class="table-responsive">
                <table class="table align-middle mb-2">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="min-width: 10rem;">Step name</th>
                            <th scope="col" style="min-width: 14rem;">Approver</th>
                            <th scope="col" style="min-width: 11rem;">Role</th>
                            <th scope="col" style="min-width: 11rem;">User</th>
                            <th scope="col" style="width: 10rem;">Only from amount</th>
                            <th scope="col" style="width: 3rem;"><span class="visually-hidden">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody data-lines>
                        @foreach ($stepRows as $index => $step)
                            @include('approvals.workflows._step', ['index' => $index, 'step' => $step])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-line-template>
                @include('approvals.workflows._step', ['index' => '__INDEX__', 'step' => []])
            </template>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-line><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add step</button>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    <button type="submit" class="btn btn-primary">Save workflow</button>
    <a href="{{ route('approval-workflows.index') }}" class="btn btn-link text-secondary">Cancel</a>
</div>
