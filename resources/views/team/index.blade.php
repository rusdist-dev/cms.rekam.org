<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...teamBoard(...), editUrl }` — see
         teamBoard.js and resources/js/extend.js. --}}
    <div x-data="teamBoard('{{ route('dash-api.team.index') }}', '{{ route('dash-api.taxonomy.show', 'team_levels') }}', {
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('team.edit', 'member')),
            },
         })">

        <x-page-header title="Tim" subtitle="Kelompokkan per level dan atur urutan dengan menyeret kartu.">
            <x-slot:actions>
                <x-button :href="route('team.create')" icon="plus">Tambah Anggota</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('team.partials.reorder-status')

        {{-- loading --}}
        <div x-show="busy" x-cloak class="space-y-6">
            @for ($g = 0; $g < 2; $g++)
                <div>
                    <div class="skeleton mb-3 h-4 w-40"></div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="rounded-card border border-gray-200 bg-white p-4">
                                <div class="flex items-center gap-3">
                                    <div class="skeleton h-12 w-12 rounded-full"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="skeleton h-4 w-2/3"></div>
                                        <div class="skeleton h-3 w-1/2"></div>
                                    </div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            @endfor
        </div>

        {{-- error --}}
        <div x-show="failed" x-cloak>
            <x-alert variant="danger" title="Gagal memuat data tim">
                <span x-text="error ?? levelsError"></span>
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="arrow-path" @click="retry()">Coba lagi</x-button>
                </x-slot:actions>
            </x-alert>
        </div>

        {{-- empty --}}
        <div x-show="blank" x-cloak>
            <x-card>
                <x-empty-state title="Belum ada anggota tim"
                               description="Tambahkan anggota lalu kelompokkan sesuai level."
                               icon="users">
                    <x-button :href="route('team.create')" size="sm" icon="plus">Tambah Anggota</x-button>
                </x-empty-state>
            </x-card>
        </div>

        {{-- ready --}}
        <div x-show="ready" x-cloak class="space-y-8">
            <template x-for="level in grouped(levels)" :key="level.value">
                <section>
                    @include('team.partials.level-group')
                </section>
            </template>

            {{-- A level deleted from the taxonomy must not hide its members. --}}
            <template x-if="orphans.length > 0">
                <section>
                    <x-alert variant="warning" title="Level tidak dikenal" class="mb-3">
                        Anggota berikut memakai level yang sudah tidak ada di Pengaturan &rsaquo; Taksonomi.
                    </x-alert>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <template x-for="member in orphans" :key="member.id">
                            <div>@include('team.partials.member-card')</div>
                        </template>
                    </div>
                </section>
            </template>
        </div>
    </div>
</x-app-layout>
