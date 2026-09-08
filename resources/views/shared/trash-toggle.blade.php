{{-- Segmented Aktif/Sampah switch for pages with a soft-delete recycle bin
     (news, events). Requires `filters.trashed` and `applyFilters()` from
     resourceTable.js — see resources/js/alpine/resourceTable.js. --}}
<div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5" role="tablist" aria-label="Status data">
    <button type="button" role="tab" :aria-selected="! filters.trashed ? 'true' : 'false'"
            @click="filters.trashed = false; applyFilters()"
            :class="! filters.trashed ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
        Aktif
    </button>
    <button type="button" role="tab" :aria-selected="filters.trashed ? 'true' : 'false'"
            @click="filters.trashed = true; applyFilters()"
            :class="filters.trashed ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
        Sampah
    </button>
</div>
