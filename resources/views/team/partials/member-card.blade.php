<div class="group flex items-start gap-3 rounded-card border border-gray-200 bg-white p-4 shadow-card transition hover:border-gray-300 hover:shadow-raised"
     :class="member.is_active ? '' : 'opacity-60'">

    <button type="button" data-drag-handle
            class="mt-1 cursor-grab rounded p-1 text-gray-300 transition hover:bg-gray-100 hover:text-gray-500 active:cursor-grabbing focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :aria-label="'Pindahkan ' + member.name">
        <x-icon name="arrows-up-down" class="h-4 w-4" />
    </button>

    <img x-show="member.photo_url" :src="member.photo_url" :alt="member.name"
         class="h-12 w-12 shrink-0 rounded-full bg-gray-100 object-cover ring-1 ring-gray-900/5">

    <span x-show="! member.photo_url"
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold uppercase text-primary-700 ring-1 ring-primary-600/10"
          x-text="member.name.split(' ').map(w => w[0]).slice(0, 2).join('')"
          :aria-label="member.name"></span>

    <div class="min-w-0 flex-1">
        <a :href="editUrl.replace('__ID__', member.id)"
           class="block truncate font-medium text-gray-900 transition hover:text-primary-600"
           x-text="member.name"></a>

        <p class="truncate text-sm text-gray-500" x-text="member.position?.id ?? '—'"></p>

        <div class="mt-1.5 flex items-center gap-2">
            <template x-if="! member.is_active">
                <x-badge variant="gray" size="sm">Nonaktif</x-badge>
            </template>
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition focus-within:opacity-100 group-hover:opacity-100">
        <a :href="editUrl.replace('__ID__', member.id)"
           class="rounded p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
           :aria-label="'Ubah ' + member.name">
            <x-icon name="pencil-square" class="h-4 w-4" />
        </a>

        <button type="button" @click="confirming = member.id"
                class="rounded p-1.5 text-gray-400 transition hover:bg-danger-50 hover:text-danger-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500"
                :aria-label="'Hapus ' + member.name">
            <x-icon name="trash" class="h-4 w-4" />
        </button>
    </div>
</div>
