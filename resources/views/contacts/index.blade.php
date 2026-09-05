<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...resourceTable(...), showUrl }` — see
         resourceTable.js and resources/js/extend.js.
         (`confirming`/`deleting` are not redeclared here: resourceTable
         already provides both, so the old copy was a dead, identical
         duplicate.) --}}
    <div x-data="resourceTable('{{ route('dash-api.contacts.index') }}', { status: '' }, {
            sort: 'created_at',
            direction: 'desc',
            extra: {
                showUrl: @js(\App\Support\RouteTemplate::for('contacts.show', 'message')),
            },
         })">

        <x-page-header title="Kotak Masuk" subtitle="Pesan yang dikirim lewat formulir kontak di website.">
            <x-slot:actions>
                <x-button :href="route('contacts.settings')" variant="secondary" icon="cog-6-tooth">
                    Informasi Kontak
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-table.toolbar search-placeholder="Cari pengirim, subjek, atau isi pesan…">
            <div class="w-full sm:w-40">
                <label for="filter-status" class="sr-only">Status</label>
                <x-form.select id="filter-status" size="sm" placeholder="Semua status"
                               :options="['unread' => 'Belum dibaca', 'read' => 'Sudah dibaca', 'archived' => 'Diarsipkan']"
                               x-model="filters.status" @change="applyFilters()" />
            </div>
        </x-table.toolbar>

        <x-table :headers="[
            'Pengirim',
            'Subjek',
            ['label' => 'Diterima', 'sort' => 'created_at'],
            ['label' => 'Status', 'sort' => 'status'],
            ['label' => '', 'align' => 'right'],
        ]">
            <template x-if="loading">
                <template x-for="i in 8" :key="i">
                    <tr>
                        @for ($c = 0; $c < 5; $c++)
                            <td class="px-4 py-3"><div class="skeleton h-4 {{ $c === 1 ? 'w-3/4' : 'w-1/2' }}"></div></td>
                        @endfor
                    </tr>
                </template>
            </template>

            <x-table.error :colspan="5" />

            <x-table.state :colspan="5" x-show="isEmpty" x-cloak>
                <x-empty-state title="Kotak masuk kosong"
                               description="Pesan dari formulir kontak website akan muncul di sini."
                               icon="inbox" />
            </x-table.state>

            <template x-for="item in items" :key="item.id">
                <tr class="transition hover:bg-gray-50"
                    :class="item.status === 'unread' ? 'bg-primary-50/30' : ''">

                    <x-table.td>
                        <div class="flex items-center gap-3">
                            <x-avatar size="sm" name-bind="item.name" />
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-900"
                                   :class="item.status === 'unread' ? 'font-semibold' : ''"
                                   x-text="item.name"></p>
                                <p class="truncate text-xs text-gray-500" x-text="item.email"></p>
                            </div>
                        </div>
                    </x-table.td>

                    <x-table.td>
                        <a :href="showUrl.replace('__ID__', item.id)"
                           class="block max-w-sm truncate text-gray-800 transition hover:text-primary-600"
                           :class="item.status === 'unread' ? 'font-medium' : ''"
                           x-text="item.subject"></a>
                        <p class="max-w-sm truncate text-xs text-gray-400" x-text="item.message"></p>
                    </x-table.td>

                    <x-table.td muted nowrap>
                        <span x-text="item.created_at"></span>
                    </x-table.td>

                    <x-table.td nowrap>
                        <template x-if="item.status === 'unread'">
                            <x-badge variant="danger" dot>Belum dibaca</x-badge>
                        </template>
                        <template x-if="item.status === 'read'">
                            <x-badge variant="gray">Sudah dibaca</x-badge>
                        </template>
                        <template x-if="item.status === 'archived'">
                            <x-badge variant="gray" icon="archive-box">Diarsipkan</x-badge>
                        </template>
                    </x-table.td>

                    <x-table.td align="right">
                        <x-table.row-actions
                            view-url="showUrl.replace('__ID__', item.id)"
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
                         title="Hapus pesan ini?"
                         loading="deleting"
                         on-confirm="deleting = true; setTimeout(() => { confirming = null; deleting = false; refresh() }, 400)">
            Pesan akan dihapus permanen dan tidak bisa dipulihkan.
        </x-modal.confirm>
    </div>
</x-app-layout>
