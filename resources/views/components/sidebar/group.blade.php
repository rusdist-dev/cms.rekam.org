@props([
    'name',
    'label',
    'icon' => null,
])

{{-- Collapsible menu group (context.md §7.4). Open/closed state is owned by the
     `sidebar` Alpine component so it survives navigation via localStorage. --}}
<div {{ $attributes->merge(['class' => 'space-y-1']) }} x-data>
    <button type="button"
            @click="toggleGroup('{{ $name }}')"
            :aria-expanded="isGroupOpen('{{ $name }}') ? 'true' : 'false'"
            aria-controls="sidebar-group-{{ $name }}"
            class="flex w-full items-center gap-3 rounded px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
        @if ($icon)
            <x-icon :name="$icon" class="h-5 w-5 text-gray-400" />
        @endif

        <span class="min-w-0 flex-1 truncate" x-show="! collapsed" x-cloak>{{ $label }}</span>

        <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                x-show="! collapsed" x-cloak
                ::class="isGroupOpen('{{ $name }}') ? 'rotate-0' : '-rotate-90'" />

        <span class="sr-only" x-show="collapsed">{{ $label }}</span>
    </button>

    <div id="sidebar-group-{{ $name }}"
         x-show="isGroupOpen('{{ $name }}') || collapsed"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="space-y-1">
        {{ $slot }}
    </div>
</div>
