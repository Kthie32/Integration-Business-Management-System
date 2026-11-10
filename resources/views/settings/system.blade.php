<x-layouts::app title="System settings">
    <x-page-header title="Settings" subtitle="Display preferences and automatic checks."
        :breadcrumbs="['Settings' => route('settings.index'), 'System' => null]" />

    @include('settings._nav')

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.system.update') }}" novalidate>
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 col-xl-4">
                        <x-form.select name="date_format" label="Date format" :value="$system['date_format']" :options="$dateFormats" required />
                    </div>
                </div>

                <x-form.checkbox name="email_notifications" label="Also send notifications by email" :checked="$system['email_notifications']" switch
                    help="In-app notifications are always on. Email needs the mail server to be configured." />
                <x-form.checkbox name="low_stock_check" label="Run the daily low-stock check" :checked="$system['low_stock_check']" switch />

                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
</x-layouts::app>
