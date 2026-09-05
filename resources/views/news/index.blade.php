<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{--
        A direct function call — never `x-data="{ ...resourceTable(...), editUrl: ... }"`.
        That spread form evaluates resourceTable's isEmpty/isReady/etc. getters
        once, right then, and bakes each into a plain frozen value (isEmpty
        stuck at `true` since items is still `[]` at that instant) — the empty
        state would never leave even once data arrives. See resourceTable.js
        and resources/js/extend.js.
    --}}
    <div x-data="resourceTable('{{ route('dash-api.news.index') }}', { status: '', category_id: '', program: '' }, {
            sort: 'published_at',
            direction: 'desc',
            deleteUrl: @js(\App\Support\RouteTemplate::for('dash-api.news.destroy', 'news')),
            bulkUrl: '{{ route('dash-api.news.bulk') }}',
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('news.edit', 'news')),
            },
         })">

        <x-page-header title="Berita" subtitle="Kelola artikel, jadwal terbit, dan program terkait.">
            <x-slot:actions>
                <x-button :href="route('news.create')" icon="plus">Tambah Berita</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-table.toolbar search-placeholder="Cari judul atau penulis…">
            @include('news.partials.filters')

            <x-slot:actions>
                @include('news.partials.bulk-actions')
            </x-slot:actions>
        </x-table.toolbar>

        <x-table :headers="[
            ['label' => 'Judul', 'sort' => 'title.id'],
            'Kategori',
            'Program',
            ['label' => 'Status', 'sort' => 'status'],
            ['label' => 'Terbit', 'sort' => 'published_at'],
            ['label' => '', 'align' => 'right'],
        ]">
            <template x-if="loading">
                <template x-for="i in 8" :key="i">
                    <tr>
                        @for ($c = 0; $c < 6; $c++)
                            <td class="px-4 py-3"><div class="skeleton h-4 {{ $c === 0 ? 'w-3/4' : 'w-1/2' }}"></div></td>
                        @endfor
                    </tr>
                </template>
            </template>

            <x-table.error :colspan="6" />

            <x-table.state :colspan="6" x-show="isEmpty" x-cloak>
                <x-empty-state title="Belum ada berita"
                               description="Mulai dengan menulis artikel pertama untuk company ini."
                               icon="newspaper">
                    <x-button :href="route('news.create')" size="sm" icon="plus">Tambah Berita</x-button>
                </x-empty-state>
            </x-table.state>

            <template x-for="item in items" :key="item.id">
                <tr class="transition hover:bg-gray-50">
                    @include('news.partials.row')
                </tr>
            </template>
        </x-table>

        <x-pagination class="mt-4" />

        {{-- onClose: "confirming !== null" is a comparison, not an
             assignable variable — Cancel/Escape/X must call cancelDelete()
             instead of the modal's default "{{ $show }} = false". --}}
        <x-modal.confirm show="confirming !== null"
                         on-close="cancelDelete()"
                         title="Hapus berita ini?"
                         confirm-label="Hapus"
                         loading="deleting"
                         on-confirm="destroy()">
            Berita akan dipindahkan ke tempat sampah dan bisa dipulihkan kembali.
        </x-modal.confirm>
    </div>
</x-app-layout>
