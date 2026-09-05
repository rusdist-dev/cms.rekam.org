{{-- The rail itself. Its open/collapsed state comes from the `sidebar` Alpine
     component declared on the layout, so both the rail and the content offset
     react to the same source. --}}

{{-- Mobile drawer backdrop --}}
<div x-show="drawerOpen" x-cloak
     @click="closeDrawer()"
     x-transition:enter="transition-opacity ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-overlay bg-gray-900/50 lg:hidden"
     aria-hidden="true"></div>

<aside {{ $attributes->merge(['class' => 'fixed inset-y-0 left-0 z-overlay flex w-sidebar flex-col border-r border-gray-200 bg-white transition-[transform,width] duration-200 lg:z-sidebar lg:translate-x-0']) }}
       :class="[
           drawerOpen ? 'translate-x-0' : '-translate-x-full',
           collapsed ? 'lg:w-sidebar-collapsed' : 'lg:w-sidebar',
       ]"
       aria-label="Menu utama">

    <x-sidebar.brand />

    <nav class="scrollbar-thin flex-1 space-y-4 overflow-y-auto px-3 py-4">
        {{ $slot }}
    </nav>

    <div class="shrink-0 border-t border-gray-200 p-3">
        {{-- Collapse toggle is desktop-only; on mobile the drawer closes instead. --}}
        <button type="button"
                @click="toggleCollapse()"
                class="hidden w-full items-center gap-3 rounded px-3 py-2 text-sm text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 lg:flex"
                :aria-label="collapsed ? 'Lebarkan menu' : 'Ciutkan menu'">
            <x-icon name="chevron-left" class="h-5 w-5 shrink-0 transition-transform"
                    ::class="collapsed ? 'rotate-180' : ''" />
            <span x-show="! collapsed" x-cloak>Ciutkan menu</span>
        </button>

        <button type="button"
                @click="closeDrawer()"
                class="flex w-full items-center gap-3 rounded px-3 py-2 text-sm text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 lg:hidden">
            <x-icon name="x-mark" class="h-5 w-5 shrink-0" />
            Tutup menu
        </button>
    </div>
</aside>
