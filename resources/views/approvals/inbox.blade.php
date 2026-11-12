@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="My approvals">
    <x-page-header title="My approvals" subtitle="Everything waiting for your decision. You never see items you raised yourself."
        :breadcrumbs="['My approvals' => null]" />

    <x-data-table :empty="$items->isEmpty()" empty-message="Nothing is waiting for your approval." empty-icon="inbox">
        <x-slot:head>
            <tr>
                <th scope="col">Type</th>
                <th scope="col">Document</th>
                <th scope="col">About</th>
                <th scope="col">Your step</th>
                <th scope="col">Raised by</th>
                <th scope="col" class="text-end">Amount</th>
                <th scope="col">Waiting since</th>
            </tr>
        </x-slot:head>

        @foreach ($items as $item)
            <tr>
                <td class="small">{{ $item['type'] }}</td>
                <td class="fw-semibold text-nowrap"><a href="{{ $item['url'] }}" class="text-decoration-none">{{ $item['number'] }}</a></td>
                <td class="small">{{ Str::limit($item['title'], 70) }}</td>
                <td class="small">{{ $item['step'] ?? 'Approval' }}</td>
                <td class="small">{{ $item['requester'] ?? '—' }}</td>
                <td class="text-end">@if ($item['amount'] !== null)<x-money :amount="$item['amount']" />@else — @endif</td>
                <td class="small text-nowrap">{{ $item['since'] ? $settings->formatDate($item['since']) : '—' }}</td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
