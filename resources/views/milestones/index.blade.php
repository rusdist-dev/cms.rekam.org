<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Direct function call, not `{ ...sortableList(...), editUrl }` — see
         sortableList.js and resources/js/extend.js. --}}
    <div x-data="sortableList('{{ route('dash-api.milestones.index') }}', [], {
            extra: {
                editUrl: @js(\App\Support\RouteTemplate::for('milestones.edit', 'milestone')),
            },
         })">

        <x-page-header title="Milestone" subtitle="Perjalanan lembaga yang tampil sebagai linimasa di website.">
            <x-slot:actions>
                <x-button :href="route('milestones.create')" icon="plus">Tambah Milestone</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('team.partials.reorder-status')

        <div x-show="loading" x-cloak class="space-y-3">
            @for ($i = 0; $i < 5; $i++)
                <div class="flex gap-4 rounded-card border border-gray-200 bg-white p-4">
                    <div class="skeleton h-12 w-16 shrink-0"></div>
                    <div class="flex-1 space-y-2">
                        <div class="skeleton h-4 w-1/3"></div>
                        <div class="skeleton h-3 w-2/3"></div>
                    </div>
                </div>
            @endfor
        </div>

        <div x-show="error && ! loading" x-cloak>
            <x-alert variant="danger" title="Gagal memuat milestone">
                <span x-text="error"></span>
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
                </x-slot:actions>
            </x-alert>
        </div>

        <div x-show="isEmpty" x-cloak>
            <x-card>
                <x-empty-state title="Belum ada milestone"
                               description="Catat pencapaian penting beserta tahunnya."
                               icon="flag">
                    <x-button :href="route('milestones.create')" size="sm" icon="plus">Tambah Milestone</x-button>
                </x-empty-state>
            </x-card>
        </div>

        {{-- Rendered as the timeline it becomes on the compro, so editors see
             the order they are arranging (plan.md §5.5 q5). --}}
        <div x-show="isReady" x-cloak
             x-sort="move($item, $position)"
             x-sort:config="{ handle: '[data-drag-handle]' }"
             class="relative space-y-3 before:absolute before:bottom-4 before:left-[4.25rem] before:top-4 before:w-px before:bg-gray-200">

            <template x-for="item in items" :key="item.id">
                <div x-sort:item="item.id" class="relative">
                    @include('milestones.partials.row')
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
                         title="Hapus milestone ini?"
                         confirm-label="Hapus"
                         loading="deleting"
                         on-confirm="destroy()">
            Milestone akan hilang dari linimasa di website.
        </x-modal.confirm>
    </div>
</x-app-layout>
