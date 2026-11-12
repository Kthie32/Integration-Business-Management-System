<x-layouts::app title="Reports">
    <x-page-header title="Reports" subtitle="Every report has filters, totals, a chart, PDF, Excel and a print view. Only the reports your role allows are listed."
        :breadcrumbs="['Reports' => null]" />

    @foreach ($groups as $group => $reports)
        <h2 class="h6 text-uppercase text-secondary mt-4 mb-3">{{ $group }}</h2>
        <div class="row g-3">
            @foreach ($reports as $entry)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h6 mb-1">
                                <a href="{{ $entry['url'] }}" class="stretched-link text-decoration-none">{{ $entry['title'] }}</a>
                            </h3>
                            <p class="small text-secondary mb-0">{{ $entry['description'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</x-layouts::app>
