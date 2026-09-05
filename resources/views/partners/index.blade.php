<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...sortableList(...), editUrl }` — see
         sortableList.js and resources/js/extend.js. --}}
    <div x-data="sortableList('{{ route('dash-api.partners.index') }}', [], {
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('partners.edit', 'partner')),
                confirming: null,
                deleting: false,
            },
         })">

        <x-page-header title="Partner" subtitle="Seret logo untuk mengatur urutan tampil di website.">
            <x-slot:actions>
                <x-button :href="route('partners.create')" icon="plus">Tambah Partner</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('team.partials.reorder-status')

        <div x-show="loading" x-cloak class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @for ($i = 0; $i < 10; $i++)
                <div class="rounded-card border border-gray-200 bg-white p-4">
                    <div class="skeleton mb-3 h-16 w-full"></div>
                    <div class="skeleton h-3.5 w-2/3"></div>
                </div>
            @endfor
        </div>

        <div x-show="error && ! loading" x-cloak>
            <x-alert variant="danger" title="Gagal memuat partner">
                <span x-text="error"></span>
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
                </x-slot:actions>
            </x-alert>
        </div>

        <div x-show="isEmpty" x-cloak>
            <x-card>
                <x-empty-state title="Belum ada partner"
                               description="Tambahkan logo mitra untuk ditampilkan di website company."
                               icon="building-office-2">
                    <x-button :href="route('partners.create')" size="sm" icon="plus">Tambah Partner</x-button>
                </x-empty-state>
            </x-card>
        </div>

        <div x-show="isReady" x-cloak
             x-sort="move($item, $position)"
             x-sort:config="{ handle: '[data-drag-handle]' }"
             class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">

            <template x-for="partner in items" :key="partner.id">
                <div x-sort:item="partner.id">
                    @include('partners.partials.card')
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
                         title="Hapus partner ini?"
                         loading="deleting"
                         on-confirm="deleting = true; setTimeout(() => { confirming = null; deleting = false; load() }, 400)">
            Logo dan data partner akan dihapus permanen.
        </x-modal.confirm>
    </div>
</x-app-layout>
