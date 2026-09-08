<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...resourceTable(...) }` — see
         resourceTable.js and resources/js/extend.js. Read-only: no deleteUrl,
         since an audit trail is never edited or deleted from here. --}}
    <div x-data="resourceTable('{{ route('dash-api.activity.index') }}', { from: '', to: '' })">

        <x-page-header title="Riwayat Aktivitas" subtitle="Jejak audit setiap perubahan konten di company ini." />

        <x-table.toolbar search-placeholder="Cari deskripsi atau nama pengguna…">
            <div class="w-full sm:w-40">
                <label for="filter-from" class="sr-only">Dari tanggal</label>
                <input id="filter-from" type="date" x-model="filters.from" @change="applyFilters()"
                       class="block w-full rounded border-gray-300 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>

            <div class="w-full sm:w-40">
                <label for="filter-to" class="sr-only">Sampai tanggal</label>
                <input id="filter-to" type="date" x-model="filters.to" @change="applyFilters()"
                       class="block w-full rounded border-gray-300 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>
        </x-table.toolbar>

        <x-table :headers="['Waktu', 'Deskripsi', 'Pengguna', 'Modul']">
            <template x-if="loading">
                <template x-for="i in 8" :key="i">
                    <tr>
                        @for ($c = 0; $c < 4; $c++)
                            <td class="px-4 py-3"><div class="skeleton h-4 {{ $c === 1 ? 'w-3/4' : 'w-1/2' }}"></div></td>
                        @endfor
                    </tr>
                </template>
            </template>

            <x-table.error :colspan="4" />

            <x-table.state :colspan="4" x-show="isEmpty" x-cloak>
                <x-empty-state title="Belum ada aktivitas"
                               description="Perubahan pada berita, events, tim dan modul lain akan tercatat di sini."
                               icon="clock" />
            </x-table.state>

            <template x-for="item in items" :key="item.id">
                <tr class="transition hover:bg-gray-50">
                    <x-table.td muted nowrap>
                        <span x-text="item.created_at"></span>
                    </x-table.td>

                    <x-table.td>
                        <span class="text-gray-800" x-text="item.description"></span>
                    </x-table.td>

                    <x-table.td nowrap>
                        <span x-text="item.causer ?? '—'"></span>
                    </x-table.td>

                    <x-table.td nowrap>
                        <x-badge variant="gray" size="sm"><span x-text="item.subject_type"></span></x-badge>
                    </x-table.td>
                </tr>
            </template>
        </x-table>

        <x-pagination class="mt-4" />
    </div>
</x-app-layout>
