{{-- Reorder feedback. A failed save rolls the list back (sortableList.persist),
     so the message explains a change the user can see reverting. --}}
<div class="mb-4 flex h-6 items-center gap-2 text-sm" aria-live="polite">
    <span x-show="saving" x-cloak class="flex items-center gap-2 text-gray-500">
        <x-spinner size="xs" />
        Menyimpan urutan…
    </span>

    <span x-show="! saving && savedAt" x-cloak class="flex items-center gap-1.5 text-success-600">
        <x-icon name="check-circle" variant="solid" class="h-4 w-4" />
        Urutan tersimpan
    </span>

    <span x-show="! saving && error && savedAt === null" x-cloak class="flex items-center gap-1.5 text-danger-600">
        <x-icon name="exclamation-circle" variant="solid" class="h-4 w-4" />
        Urutan gagal disimpan dan dikembalikan seperti semula.
    </span>
</div>
