<div class="group flex h-full items-start gap-3 rounded-card border border-gray-200 bg-white p-4 shadow-card transition hover:border-gray-300 hover:shadow-raised"
     :class="unit.is_active ? '' : 'opacity-60'">

    <button type="button" data-drag-handle
            class="mt-1 cursor-grab rounded p-1 text-gray-300 transition hover:bg-gray-100 hover:text-gray-500 active:cursor-grabbing focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :aria-label="'Pindahkan ' + unit.name">
        <x-icon name="arrows-up-down" class="h-4 w-4" />
    </button>

    <img x-show="unit.logo_url" :src="unit.logo_url" :alt="unit.name"
         class="h-12 w-12 shrink-0 rounded object-contain">

    <span x-show="! unit.logo_url"
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded bg-primary-50 text-primary-600">
        <x-icon name="squares-plus" class="h-6 w-6" />
    </span>

    <div class="min-w-0 flex-1">
        <a :href="editUrl.replace('__ID__', unit.id)"
           class="block truncate font-medium text-gray-900 transition hover:text-primary-600"
           x-text="unit.name"></a>

        <a :href="unit.url" target="_blank" rel="noopener"
           class="mt-0.5 flex items-center gap-1 truncate text-xs text-primary-600 transition hover:underline">
            <x-icon name="globe-alt" class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate" x-text="unit.domain"></span>
        </a>

        <p class="mt-1.5 line-clamp-2 text-sm text-gray-500" x-text="unit.description?.id ?? ''"></p>
    </div>

    <x-table.row-actions
        edit-url="editUrl.replace('__ID__', unit.id)"
        on-delete="confirming = unit.id"
        class="shrink-0 opacity-0 transition focus-within:opacity-100 group-hover:opacity-100" />
</div>
