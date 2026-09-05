<div class="mb-3 flex items-center gap-3">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500" x-text="level.label"></h2>
    <x-badge variant="gray" size="sm"><span x-text="level.items.length"></span></x-badge>
    <div class="h-px flex-1 bg-gray-200"></div>
</div>

<template x-if="level.items.length === 0">
    <div class="rounded-card border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-400">
        Belum ada anggota di level ini.
    </div>
</template>

{{-- Dragging is scoped to the level: order changes inside a group, never
     across groups (plan.md §5.3.b). --}}
<div x-show="level.items.length > 0"
     x-sort="move($item, $position, level.value)"
     x-sort:config="{ handle: '[data-drag-handle]' }"
     class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">

    <template x-for="member in level.items" :key="member.id">
        <div x-sort:item="member.id">
            @include('team.partials.member-card')
        </div>
    </template>
</div>
