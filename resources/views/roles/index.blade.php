<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...resourceTable(...), editUrl }` — see
         resourceTable.js and resources/js/extend.js. --}}
    <div x-data="resourceTable('{{ route('dash-api.roles.index') }}', {}, {
            sort: 'id',
            direction: 'asc',
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('roles.edit', 'role')),
            },
         })">

        <x-page-header title="Peran & Izin" subtitle="Kelompok izin yang bisa diberikan ke pengguna.">
            <x-slot:actions>
                <x-button :href="route('roles.create')" icon="plus">Tambah Peran</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-table.toolbar search-placeholder="Cari peran…" />

        <x-table :headers="[
            ['label' => 'Peran', 'sort' => 'label'],
            ['label' => 'Pengguna', 'align' => 'center'],
            ['label' => 'Izin', 'align' => 'center'],
            ['label' => '', 'align' => 'right'],
        ]">
            <template x-if="loading">
                <template x-for="i in 4" :key="i">
                    <tr>
                        @for ($c = 0; $c < 4; $c++)
                            <td class="px-4 py-3"><div class="skeleton h-4 {{ $c === 0 ? 'w-3/4' : 'w-10' }}"></div></td>
                        @endfor
                    </tr>
                </template>
            </template>

            <x-table.error :colspan="4" />

            <x-table.state :colspan="4" x-show="isEmpty" x-cloak>
                <x-empty-state title="Belum ada peran" icon="shield-check">
                    <x-button :href="route('roles.create')" size="sm" icon="plus">Tambah Peran</x-button>
                </x-empty-state>
            </x-table.state>

            <template x-for="item in items" :key="item.id">
                <tr class="transition hover:bg-gray-50">
                    <x-table.td>
                        <a :href="editUrl.replace('__ID__', item.id)"
                           class="block font-medium text-gray-900 transition hover:text-primary-600"
                           x-text="item.label"></a>
                        <p class="font-mono text-xs text-gray-400" x-text="item.name"></p>
                    </x-table.td>

                    <x-table.td align="center" muted>
                        <span x-text="item.users_count"></span>
                    </x-table.td>

                    <x-table.td align="center" muted>
                        <span x-text="item.permissions_count"></span>
                    </x-table.td>

                    <x-table.td align="right">
                        {{-- super-admin holds every permission by definition, so
                             editing it could only ever lock someone out. --}}
                        <template x-if="item.name !== 'super-admin'">
                            <div>
                                <x-table.row-actions edit-url="editUrl.replace('__ID__', item.id)" />
                            </div>
                        </template>

                        <template x-if="item.name === 'super-admin'">
                            <x-badge variant="gray" size="sm">Terkunci</x-badge>
                        </template>
                    </x-table.td>
                </tr>
            </template>
        </x-table>

        <x-pagination class="mt-4" />
    </div>
</x-app-layout>
