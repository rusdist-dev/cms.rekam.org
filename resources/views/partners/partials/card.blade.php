<div class="group relative flex h-full flex-col rounded-card border border-gray-200 bg-white p-4 shadow-card transition hover:border-gray-300 hover:shadow-raised"
     :class="partner.is_active ? '' : 'opacity-60'">

    <div class="mb-3 flex items-start justify-between">
        <button type="button" data-drag-handle
                class="-ms-1 cursor-grab rounded p-1 text-gray-300 transition hover:bg-gray-100 hover:text-gray-500 active:cursor-grabbing focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                :aria-label="'Pindahkan ' + partner.name">
            <x-icon name="arrows-up-down" class="h-4 w-4" />
        </button>

        <div class="flex items-center gap-0.5 opacity-0 transition focus-within:opacity-100 group-hover:opacity-100">
            <a :href="editUrl.replace('__ID__', partner.id)"
               class="rounded p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
               :aria-label="'Ubah ' + partner.name">
                <x-icon name="pencil-square" class="h-4 w-4" />
            </a>

            <button type="button" @click="confirming = partner.id"
                    class="rounded p-1.5 text-gray-400 transition hover:bg-danger-50 hover:text-danger-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500"
                    :aria-label="'Hapus ' + partner.name">
                <x-icon name="trash" class="h-4 w-4" />
            </button>
        </div>
    </div>

    <div class="flex h-16 items-center justify-center rounded bg-gray-50">
        <img x-show="partner.logo_url" :src="partner.logo_url" :alt="partner.name"
             class="max-h-12 max-w-full object-contain">

        <span x-show="! partner.logo_url" class="text-gray-300">
            <x-icon name="building-office-2" class="h-7 w-7" />
        </span>
    </div>

    <div class="mt-3 min-w-0">
        <p class="truncate text-sm font-medium text-gray-900" x-text="partner.name"></p>
        <p class="truncate text-xs text-gray-500" x-text="partner.title?.id ?? '—'"></p>
    </div>

    <template x-if="! partner.is_active">
        <span class="mt-2">
            <x-badge variant="gray" size="sm">Disembunyikan</x-badge>
        </span>
    </template>
</div>
