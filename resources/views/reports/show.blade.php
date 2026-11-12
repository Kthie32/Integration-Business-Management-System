@php
    $query = $filters->query();
    $sortLink = function (string $key) use ($query, $filters, $report): string {
        $direction = $filters->sort === $key && $filters->direction === 'asc' ? 'desc' : 'asc';

        return route('reports.show', [$report->key(), ...array_merge($query, ['sort' => $key, 'direction' => $direction])]);
    };
    $statIcons = ['money' => 'cash-coin', 'number' => 'hash', 'percent' => 'percent', 'text' => 'star'];
@endphp
<x-layouts::app :title="$report->title()">
    <x-page-header :title="$report->title()" :subtitle="$subtitle"
        :breadcrumbs="['Reports' => route('reports.index'), $report->title() => null]">
        <x-slot:actions>
            <a href="{{ route('reports.export', [$report->key(), 'print', ...$query]) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>Print
            </a>
            @if ($canExport)
                <a href="{{ route('reports.export', [$report->key(), 'pdf', ...$query]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>PDF
                </a>
                <a href="{{ route('reports.export', [$report->key(), 'xlsx', ...$query]) }}" class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Excel
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($report->usesPeriod() || $report->supportsDepartment() || $report->selectFilters() !== [])
        <x-filter-bar :action="route('reports.show', $report->key())" :reset-url="route('reports.show', $report->key())">
            @if ($report->usesPeriod())
                <div class="col-sm-6 col-md-2">
                    <label for="from" class="form-label small">From</label>
                    <input type="date" name="from" id="from" class="form-control" value="{{ $filters->from->toDateString() }}">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label for="to" class="form-label small">To</label>
                    <input type="date" name="to" id="to" class="form-control" value="{{ $filters->to->toDateString() }}">
                </div>
            @endif
            @if ($report->supportsDepartment())
                <div class="col-sm-6 col-md-2">
                    <label for="department" class="form-label small">Department</label>
                    <select name="department" id="department" class="form-select">
                        <option value="">All</option>
                        @foreach ($departments as $id => $name)
                            <option value="{{ $id }}" @selected($filters->departmentId === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @foreach ($report->selectFilters() as $name => $filter)
                <div class="col-sm-6 col-md-2">
                    <label for="filter-{{ $name }}" class="form-label small">{{ $filter['label'] }}</label>
                    <select name="{{ $name }}" id="filter-{{ $name }}" class="form-select">
                        <option value="">{{ $name === 'group' ? 'Default' : 'All' }}</option>
                        @foreach ($filter['options'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters->option($name) === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            @if ($filters->sort)
                <input type="hidden" name="sort" value="{{ $filters->sort }}">
                <input type="hidden" name="direction" value="{{ $filters->direction }}">
            @endif
        </x-filter-bar>
    @endif

    @if ($result->summary !== [])
        <div class="row g-3 mb-3">
            @foreach ($result->summary as $stat)
                <div class="col-sm-6 col-xl">
                    <x-stat-card :label="$stat['label']" :value="$formatter->value($stat['type'], $stat['value'])" :icon="$statIcons[$stat['type']] ?? 'bar-chart'" />
                </div>
            @endforeach
        </div>
    @endif

    @if ($result->chart !== null && $result->rows !== [] && $result->chart['labels'] !== [])
        <div class="card mb-3">
            <div class="card-body">
                <div class="report-chart" style="position: relative; height: 280px;">
                    <canvas data-chart="{{ json_encode($result->chart) }}" role="img" aria-label="Chart for {{ $report->title() }}"></canvas>
                </div>
            </div>
        </div>
    @endif

    <x-data-table :empty="$result->rows === []" empty-message="No records match these filters." empty-icon="bar-chart">
        <x-slot:head>
            <tr>
                @foreach ($result->columns as $column)
                    <th scope="col" @class(['text-end' => $column->isNumeric(), 'text-nowrap'])>
                        @if ($column->sortable)
                            <a href="{{ $sortLink($column->key) }}" class="text-reset text-decoration-none">
                                {{ $column->label }}
                                @if ($filters->sort === $column->key)
                                    <i class="bi bi-caret-{{ $filters->direction === 'asc' ? 'up' : 'down' }}-fill small" aria-label="sorted {{ $filters->direction === 'asc' ? 'ascending' : 'descending' }}"></i>
                                @endif
                            </a>
                        @else
                            {{ $column->label }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </x-slot:head>
        @foreach (array_slice($result->rows, 0, $rowLimit) as $row)
            <tr>
                @foreach ($result->columns as $column)
                    <td @class(['text-end text-nowrap' => $column->isNumeric()])>{{ $formatter->cell($column, $row[$column->key] ?? null) }}</td>
                @endforeach
            </tr>
        @endforeach
        @if ($result->totals !== null)
            <tr class="table-light fw-semibold">
                @foreach ($result->columns as $column)
                    <td @class(['text-end text-nowrap' => $column->isNumeric()])>
                        @if (array_key_exists($column->key, $result->totals))
                            {{ $column->isNumeric() ? $formatter->cell($column, $result->totals[$column->key]) : $result->totals[$column->key] }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endif
    </x-data-table>

    @if (count($result->rows) > $rowLimit)
        <p class="small text-secondary mt-2">Showing the first {{ number_format($rowLimit) }} of {{ number_format(count($result->rows)) }} rows. Totals cover every row; use PDF or Excel for the full list.</p>
    @endif

    @foreach ($result->notes as $note)
        <p class="small text-secondary mt-2 mb-0">{{ $note }}</p>
    @endforeach
</x-layouts::app>
