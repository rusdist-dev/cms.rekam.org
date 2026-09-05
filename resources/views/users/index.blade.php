<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...resourceTable(...), editUrl }` — see
         resourceTable.js and resources/js/extend.js. --}}
    <div x-data="resourceTable('{{ route('dash-api.users.index') }}', { role: '', is_active: '' }, {
            sort: 'name',
            direction: 'asc',
            deleteUrl: @js(\App\Support\RouteTemplate::for('dash-api.users.destroy', 'user')),
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('users.edit', 'user')),
            },
         })">

        <x-page-header title="Pengguna" subtitle="Akun yang dapat masuk ke CMS ini.">
            <x-slot:actions>
                <x-button :href="route('users.create')" icon="plus">Tambah Pengguna</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-table.toolbar search-placeholder="Cari nama atau email…">
            <div class="w-full sm:w-40" x-data="apiResource('{{ route('dash-api.roles.index') }}', [])">
                <label for="filter-role" class="sr-only">Peran</label>
                <select id="filter-role" x-model="filters.role" @change="applyFilters()"
                        :disabled="loading"
                        class="block w-full rounded border-gray-300 py-1.5 pe-9 ps-3 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-50">
                    <option value="">Semua peran</option>
                    <template x-for="role in (data ?? [])" :key="role.id">
                        <option :value="role.name" x-text="role.label"></option>
                    </template>
                </select>
            </div>

            <div class="w-full sm:w-36">
                <label for="filter-active" class="sr-only">Status</label>
                <x-form.select id="filter-active" size="sm" placeholder="Semua status"
                               :options="['1' => 'Aktif', '0' => 'Nonaktif']"
                               x-model="filters.is_active" @change="applyFilters()" />
            </div>
        </x-table.toolbar>

        <x-table :headers="[
            ['label' => 'Nama', 'sort' => 'name'],
            'Peran',
            'Company',
            ['label' => 'Terakhir Masuk', 'sort' => 'last_login_at'],
            'Status',
            ['label' => '', 'align' => 'right'],
        ]">
            <template x-if="loading">
                <template x-for="i in 6" :key="i">
                    <tr>
                        @for ($c = 0; $c < 6; $c++)
                            <td class="px-4 py-3"><div class="skeleton h-4 {{ $c === 0 ? 'w-3/4' : 'w-1/2' }}"></div></td>
                        @endfor
                    </tr>
                </template>
            </template>

            <x-table.error :colspan="6" />

            <x-table.state :colspan="6" x-show="isEmpty" x-cloak>
                <x-empty-state title="Belum ada pengguna" icon="user-group">
                    <x-button :href="route('users.create')" size="sm" icon="plus">Tambah Pengguna</x-button>
                </x-empty-state>
            </x-table.state>

            <template x-for="item in items" :key="item.id">
                <tr class="transition hover:bg-gray-50">
                    <x-table.td>
                        <div class="flex items-center gap-3">
                            <x-avatar size="sm" name-bind="item.name" src-bind="item.avatar_url" />
                            <div class="min-w-0">
                                <a :href="editUrl.replace('__ID__', item.id)"
                                   class="block truncate font-medium text-gray-900 transition hover:text-primary-600"
                                   x-text="item.name"></a>
                                <p class="truncate text-xs text-gray-500" x-text="item.email"></p>
                            </div>
                        </div>
                    </x-table.td>

                    <x-table.td nowrap>
                        <x-badge ::variant="item.role === 'super-admin' ? 'primary' : 'gray'" size="sm">
                            <span x-text="item.role_label"></span>
                        </x-badge>
                    </x-table.td>

                    <x-table.td>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="slug in (item.tenants ?? [])" :key="slug">
                                <x-badge variant="gray" size="sm"><span x-text="slug"></span></x-badge>
                            </template>
                        </div>
                    </x-table.td>

                    <x-table.td muted nowrap>
                        <span x-text="item.last_login_at ?? 'Belum pernah'"></span>
                    </x-table.td>

                    <x-table.td nowrap>
                        <template x-if="item.is_active">
                            <x-badge variant="success" dot>Aktif</x-badge>
                        </template>
                        <template x-if="! item.is_active">
                            <x-badge variant="gray" dot>Nonaktif</x-badge>
                        </template>
                    </x-table.td>

                    <x-table.td align="right">
                        <x-table.row-actions
                            edit-url="editUrl.replace('__ID__', item.id)"
                            on-delete="confirmDelete(item.id)" />
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
                         title="Hapus pengguna ini?"
                         loading="deleting"
                         on-confirm="destroy()">
            Akun akan dihapus dan pengguna langsung kehilangan akses. Untuk mencabut akses
            sementara, gunakan tombol nonaktif pada formulir pengguna.
        </x-modal.confirm>
    </div>
</x-app-layout>
