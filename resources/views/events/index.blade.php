<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...resourceTable(...), editUrl }` — see
         resourceTable.js and resources/js/extend.js. --}}
    <div x-data="resourceTable('{{ route('dash-api.events.index') }}', { status: '', category: '' }, {
            sort: 'start_at',
            direction: 'desc',
            deleteUrl: @js(\App\Support\RouteTemplate::for('dash-api.events.destroy', 'event')),
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('events.edit', 'event')),
            },
         })">

        <x-page-header title="Events" subtitle="Kelola acara, biaya, kuota, dan rundown.">
            <x-slot:actions>
                <x-button :href="route('events.create')" icon="plus">Tambah Event</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-table.toolbar search-placeholder="Cari judul atau lokasi…">
            <div class="w-full sm:w-40">
                <label for="filter-status" class="sr-only">Status</label>
                <x-form.select id="filter-status" size="sm" placeholder="Semua status"
                               :options="config('cms.statuses')"
                               x-model="filters.status" @change="applyFilters()" />
            </div>

            <div class="w-full sm:w-44" x-data="apiResource('{{ route('dash-api.taxonomy.show', 'event_categories') }}', [])">
                <label for="filter-category" class="sr-only">Kategori</label>
                <select id="filter-category" x-model="filters.category" @change="applyFilters()"
                        :disabled="loading"
                        class="block w-full rounded border-gray-300 py-1.5 pe-9 ps-3 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-50">
                    <option value="">Semua kategori</option>
                    <template x-for="opt in (data ?? [])" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
        </x-table.toolbar>

        <x-table :headers="[
            ['label' => 'Acara', 'sort' => 'title.id'],
            ['label' => 'Jadwal', 'sort' => 'start_at'],
            'Lokasi',
            'Biaya',
            'Kuota',
            ['label' => 'Status', 'sort' => 'status'],
            ['label' => '', 'align' => 'right'],
        ]">
            <template x-if="loading">
                <template x-for="i in 6" :key="i">
                    <tr>
                        @for ($c = 0; $c < 7; $c++)
                            <td class="px-4 py-3"><div class="skeleton h-4 {{ $c === 0 ? 'w-3/4' : 'w-1/2' }}"></div></td>
                        @endfor
                    </tr>
                </template>
            </template>

            <x-table.error :colspan="7" />

            <x-table.state :colspan="7" x-show="isEmpty" x-cloak>
                <x-empty-state title="Belum ada event"
                               description="Buat acara pertama agar tampil di website company."
                               icon="calendar-days">
                    <x-button :href="route('events.create')" size="sm" icon="plus">Tambah Event</x-button>
                </x-empty-state>
            </x-table.state>

            <template x-for="item in items" :key="item.id">
                <tr class="transition hover:bg-gray-50">
                    @include('events.partials.row')
                </tr>
            </template>
        </x-table>

        <x-pagination class="mt-4" />

        {{-- onClose: "confirming !== null" is a comparison, not an
             assignable variable — Cancel/Escape/X must call cancelDelete()
             instead of the modal's default "{{ $show }} = false". --}}
        <x-modal.confirm show="confirming !== null"
                         on-close="cancelDelete()"
                         title="Hapus event ini?"
                         loading="deleting"
                         on-confirm="destroy()">
            Event beserta rundown-nya akan dihapus.
        </x-modal.confirm>
    </div>
</x-app-layout>
