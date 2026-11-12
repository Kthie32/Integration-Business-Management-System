<x-layouts::app title="Approval workflows">
    <x-page-header title="Approval workflows"
        subtitle="Who approves what, in which order. The active workflow with the highest minimum amount that a request reaches is used. Requests already in approval keep the steps they started with."
        :breadcrumbs="['Approval workflows' => null]">
        <x-slot:actions>
            @can('create', App\Models\ApprovalWorkflow::class)
                <a href="{{ route('approval-workflows.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New workflow</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($workflows->where('is_active', true)->isEmpty())
        <div class="alert alert-warning small">No active workflow: every request goes to a single step that anyone holding the approve permission (other than the requester) can decide.</div>
    @endif

    <x-data-table :empty="$workflows->isEmpty()" empty-message="No workflows yet." empty-icon="diagram-2">
        <x-slot:head>
            <tr>
                <th scope="col">Workflow</th>
                <th scope="col">Applies to</th>
                <th scope="col">Steps</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
        </x-slot:head>

        @foreach ($workflows as $workflow)
            <tr>
                <td>
                    <span class="fw-semibold">{{ $workflow->name }}</span>
                    @if ($workflow->description)<div class="small text-secondary">{{ $workflow->description }}</div>@endif
                </td>
                <td class="small">
                    {{ $workflow->document_type->label() }}s
                    <div class="text-secondary">
                        @if ($workflow->min_amount !== null)
                            from <x-money :amount="(string) $workflow->min_amount" />
                        @else
                            any amount
                        @endif
                    </div>
                </td>
                <td class="small">
                    <ol class="mb-0 ps-3">
                        @foreach ($workflow->steps as $step)
                            <li>
                                {{ $step->displayName() }}
                                <span class="text-secondary">— {{ $step->approver_type->label() }}@if ($step->approver_type === App\Enums\ApproverType::Role): {{ $step->role?->name ?? 'deleted role' }}@elseif ($step->approver_type === App\Enums\ApproverType::User): {{ $step->user?->name ?? 'removed user' }}@endif</span>
                                @if ($step->min_amount !== null)<span class="text-secondary">(from <x-money :amount="(string) $step->min_amount" />)</span>@endif
                            </li>
                        @endforeach
                    </ol>
                </td>
                <td>
                    @if ($workflow->is_active)
                        <x-status-badge label="Active" color="success" />
                    @else
                        <x-status-badge label="Inactive" color="secondary" />
                    @endif
                </td>
                <td class="text-end text-nowrap">
                    @can('update', $workflow)
                        <a href="{{ route('approval-workflows.edit', $workflow) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    @endcan
                    @can('delete', $workflow)
                        <form method="POST" action="{{ route('approval-workflows.destroy', $workflow) }}" class="d-inline" data-confirm="Delete the workflow {{ $workflow->name }}? Requests already in approval are not affected.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete {{ $workflow->name }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </form>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
