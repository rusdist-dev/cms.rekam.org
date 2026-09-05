<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...resourceTable(...), editUrl }` — see
         resourceTable.js and resources/js/extend.js.
         (`confirming`/`deleting` are not redeclared here: resourceTable
         already provides both, so the old copy was a dead, identical
         duplicate.) --}}
    <div x-data="resourceTable('{{ route('dash-api.publications.index') }}', { category: '', is_featured: '' }, {
            sort: 'sort_order',
            direction: 'asc',
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('publications.edit', 'publication')),
            },
         })">

        <x-page-header title="Publikasi" subtitle="Kelola laporan, panduan, dan dokumen unduhan.">
            <x-slot:actions>
                <x-button :href="route('publications.create')" icon="plus">Tambah Publikasi</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-table.toolbar search-placeholder="Cari judul atau nama berkas…">
            <div class="w-full sm:w-44" x-data="apiResource('{{ route('dash-api.taxonomy.show', 'publication_categories') }}', [])">
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

            <div class="w-full sm:w-40">
                <label for="filter-featured" class="sr-only">Unggulan</label>
                <x-form.select id="filter-featured" size="sm" placeholder="Semua"
                               :options="['1' => 'Unggulan', '0' => 'Biasa']"
                               x-model="filters.is_featured" @change="applyFilters()" />
            </div>
        </x-table.toolbar>

        <x-table :headers="[
            ['label' => 'Judul', 'sort' => 'title.id'],
            'Kategori',
            'Berkas',
            ['label' => 'Unggulan', 'align' => 'center'],
            ['label' => '', 'align' => 'right'],
        ]">
            <template x-if="loading">
                <template x-for="i in 6" :key="i">
                    <tr>
                        @for ($c = 0; $c < 5; $c++)
                            <td class="px-4 py-3"><div class="skeleton h-4 {{ $c === 0 ? 'w-3/4' : 'w-1/2' }}"></div></td>
                        @endfor
                    </tr>
                </template>
            </template>

            <x-table.error :colspan="5" />

            <x-table.state :colspan="5" x-show="isEmpty" x-cloak>
                <x-empty-state title="Belum ada publikasi"
                               description="Unggah laporan atau dokumen pertama."
                               icon="document-text">
                    <x-button :href="route('publications.create')" size="sm" icon="plus">Tambah Publikasi</x-button>
                </x-empty-state>
            </x-table.state>

            <template x-for="item in items" :key="item.id">
                <tr class="transition hover:bg-gray-50">
                    <x-table.td>
                        <a :href="editUrl.replace('__ID__', item.id)"
                           class="block max-w-md truncate font-medium text-gray-900 transition hover:text-primary-600"
                           x-text="item.title.id"></a>
                    </x-table.td>

                    <x-table.td muted nowrap>
                        <x-badge variant="gray" size="sm"><span x-text="item.category"></span></x-badge>
                    </x-table.td>

                    <x-table.td muted>
                        <span class="flex items-center gap-1.5">
                            <x-icon name="document-text" class="h-4 w-4 text-danger-500" />
                            <span class="max-w-[14rem] truncate text-xs" x-text="item.file_name ?? '—'"></span>
                        </span>
                    </x-table.td>

                    <x-table.td align="center">
                        <template x-if="item.is_featured">
                            <span class="inline-flex text-warning-500" title="Publikasi unggulan">
                                <x-icon name="star" variant="solid" class="h-4 w-4" />
                            </span>
                        </template>
                        <template x-if="! item.is_featured">
                            <span class="text-xs text-gray-300">—</span>
                        </template>
                    </x-table.td>

                    <x-table.td align="right">
                        <x-table.row-actions
                            edit-url="editUrl.replace('__ID__', item.id)"
                            on-delete="confirming = item.id" />
                    </x-table.td>
                </tr>
            </template>
        </x-table>

        <x-pagination class="mt-4" />

        {{-- onClose: "confirming !== null" is a comparison, not an
             assignable variable — Cancel/Escape/X must call cancelDelete()
             instead of the modal's default "{{ $show }} = false". --}}
        <x-modal.confirm show="confirming !== null"
                         on-close="cancelDelete()"
                         title="Hapus publikasi ini?"
                         loading="deleting"
                         on-confirm="deleting = true; setTimeout(() => { confirming = null; deleting = false; refresh() }, 400)">
            Berkas PDF yang terlampir juga akan dihapus.
        </x-modal.confirm>
    </div>
</x-app-layout>
