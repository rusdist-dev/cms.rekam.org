{{-- Reads `meta` and `filters` from the surrounding resourceTable component. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-between gap-3 sm:flex-row']) }}
     x-show="isReady || loading" x-cloak>

    <p class="text-sm text-gray-500">
        Menampilkan
        <span class="font-medium text-gray-700"
              x-text="meta.total === 0 ? 0 : ((meta.current_page - 1) * meta.per_page + 1)"></span>
        –
        <span class="font-medium text-gray-700"
              x-text="Math.min(meta.current_page * meta.per_page, meta.total)"></span>
        dari
        <span class="font-medium text-gray-700" x-text="meta.total"></span>
        data
    </p>

    <nav class="flex items-center gap-1" aria-label="Navigasi halaman" x-show="meta.last_page > 1">
        <button type="button"
                @click="goToPage(meta.current_page - 1)"
                :disabled="meta.current_page <= 1"
                class="rounded p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                aria-label="Halaman sebelumnya">
            <x-icon name="chevron-left" class="h-4 w-4" />
        </button>

        <span class="px-3 text-sm text-gray-600">
            Halaman <span class="font-medium text-gray-900" x-text="meta.current_page"></span>
            dari <span class="font-medium text-gray-900" x-text="meta.last_page"></span>
        </span>

        <button type="button"
                @click="goToPage(meta.current_page + 1)"
                :disabled="meta.current_page >= meta.last_page"
                class="rounded p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                aria-label="Halaman berikutnya">
            <x-icon name="chevron-right" class="h-4 w-4" />
        </button>
    </nav>
</div>
