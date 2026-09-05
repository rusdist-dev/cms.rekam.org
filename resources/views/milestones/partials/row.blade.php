<div class="group flex items-start gap-4 rounded-card border border-gray-200 bg-white p-4 shadow-card transition hover:border-gray-300"
     :class="item.is_active ? '' : 'opacity-60'">

    <button type="button" data-drag-handle
            class="mt-1 cursor-grab rounded p-1 text-gray-300 transition hover:bg-gray-100 hover:text-gray-500 active:cursor-grabbing focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :aria-label="'Pindahkan ' + item.title.id">
        <x-icon name="arrows-up-down" class="h-4 w-4" />
    </button>

    <span class="relative z-10 flex h-10 w-14 shrink-0 items-center justify-center rounded bg-primary-600 text-sm font-semibold text-white"
          x-text="item.year ?? '—'"></span>

    <div class="min-w-0 flex-1">
        <a :href="editUrl.replace('__ID__', item.id)"
           class="block truncate font-medium text-gray-900 transition hover:text-primary-600"
           x-text="item.title.id"></a>

        <p class="mt-0.5 line-clamp-2 text-sm text-gray-500" x-text="item.body?.id ?? ''"></p>

        <template x-if="! item.is_active">
            <span class="mt-2 inline-block"><x-badge variant="gray" size="sm">Disembunyikan</x-badge></span>
        </template>
    </div>

    <img x-show="item.cover_url" :src="item.cover_url" alt=""
         class="h-14 w-20 shrink-0 rounded object-cover">

    <x-table.row-actions
        edit-url="editUrl.replace('__ID__', item.id)"
        on-delete="confirming = item.id"
        class="shrink-0" />
</div>
