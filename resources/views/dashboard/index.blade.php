<x-app-layout :title="$title">
    <x-page-header title="Dasbor" subtitle="Ringkasan konten company yang sedang aktif." />

    @include('dashboard.partials.summary')

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="Tren Publikasi" subtitle="12 bulan terakhir" class="lg:col-span-2">
            <x-chart type="line" :endpoint="route('dash-api.stats.show', 'publishing_trend')" height="h-72" />
        </x-card>

        <x-card title="Status Berita">
            <x-chart type="doughnut" :endpoint="route('dash-api.stats.show', 'status_breakdown')" height="h-72" />
        </x-card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        @include('dashboard.partials.activity')
        @include('dashboard.partials.popular')
    </div>
</x-app-layout>
