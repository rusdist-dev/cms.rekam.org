<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...apiResource(...), editUrl }` — see
         apiResource.js and resources/js/extend.js. --}}
    <div x-data="apiResource('{{ route('dash-api.tenants.index') }}', [], {
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('tenants.edit', 'tenant')),
                featureLabels: @js(collect(config('cms.features'))->map->label),
            },
         })">

        <x-page-header title="Company"
                       subtitle="Company yang dikelola CMS ini beserta modul yang aktif." />

        {{-- loading --}}
        <div x-show="loading" x-cloak class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @for ($i = 0; $i < 2; $i++)
                <div class="rounded-card border border-gray-200 bg-white p-6">
                    <div class="flex items-center gap-3">
                        <div class="skeleton h-11 w-11 rounded-card"></div>
                        <div class="flex-1 space-y-2">
                            <div class="skeleton h-4 w-1/3"></div>
                            <div class="skeleton h-3 w-1/2"></div>
                        </div>
                    </div>
                    <div class="skeleton mt-5 h-6 w-full"></div>
                </div>
            @endfor
        </div>

        {{-- error --}}
        <div x-show="error" x-cloak>
            <x-alert variant="danger" title="Gagal memuat daftar company">
                <span x-text="error"></span>
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
                </x-slot:actions>
            </x-alert>
        </div>

        {{-- empty --}}
        <div x-show="isEmpty" x-cloak>
            <x-card>
                <x-empty-state title="Belum ada company"
                               description="Company didaftarkan lewat seeder, lalu database-nya dimigrasi."
                               icon="building-storefront" />
            </x-card>
        </div>

        {{-- ready --}}
        <div x-show="isReady" x-cloak class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <template x-for="tenant in data" :key="tenant.id">
                <div>@include('tenants.partials.card')</div>
            </template>
        </div>

        <x-alert variant="info" class="mt-6">
            Menambah company baru memerlukan database baru dan menjalankan
            <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">php artisan tenants:migrate</code>.
            Prosedurnya ada di README.
        </x-alert>
    </div>
</x-app-layout>
