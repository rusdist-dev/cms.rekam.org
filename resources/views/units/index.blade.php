<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...sortableList(...), editUrl }` — see
         sortableList.js and resources/js/extend.js. --}}
    <div x-data="sortableList('{{ route('dash-api.units.index') }}', [], {
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('units.edit', 'unit')),
                confirming: null,
                deleting: false,
            },
         })">

        <x-page-header title="Unit" subtitle="Unit atau lembaga di bawah naungan company ini.">
            <x-slot:actions>
                <x-button :href="route('units.create')" icon="plus">Tambah Unit</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('team.partials.reorder-status')

        <div x-show="loading" x-cloak class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @for ($i = 0; $i < 4; $i++)
                <div class="rounded-card border border-gray-200 bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div class="skeleton h-12 w-12 rounded"></div>
                        <div class="flex-1 space-y-2">
                            <div class="skeleton h-4 w-2/3"></div>
                            <div class="skeleton h-3 w-1/2"></div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>

        <div x-show="error && ! loading" x-cloak>
            <x-alert variant="danger" title="Gagal memuat unit">
                <span x-text="error"></span>
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
                </x-slot:actions>
            </x-alert>
        </div>

        <div x-show="isEmpty" x-cloak>
            <x-card>
                <x-empty-state title="Belum ada unit"
                               description="Tambahkan unit beserta domain tujuannya."
                               icon="squares-plus">
                    <x-button :href="route('units.create')" size="sm" icon="plus">Tambah Unit</x-button>
                </x-empty-state>
            </x-card>
        </div>

        <div x-show="isReady" x-cloak
             x-sort="move($item, $position)"
             x-sort:config="{ handle: '[data-drag-handle]' }"
             class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">

            <template x-for="unit in items" :key="unit.id">
                <div x-sort:item="unit.id">
                    @include('units.partials.card')
                </div>
            </template>
        </div>

        {{-- onClose: "confirming !== null" is a comparison, not an
             assignable variable — Cancel/Escape/X must reset `confirming`
             directly instead of the modal's default "{{ $show }} = false".
             (sortableList has no cancelDelete() method — that only exists on
             resourceTable — so the reset is inline here.) --}}
        <x-modal.confirm show="confirming !== null"
                         on-close="confirming = null"
                         title="Hapus unit ini?"
                         loading="deleting"
                         on-confirm="deleting = true; setTimeout(() => { confirming = null; deleting = false; load() }, 400)">
            Unit akan hilang dari daftar di website company.
        </x-modal.confirm>
    </div>
</x-app-layout>
