{{-- Only appears once rows are selected, so the toolbar stays quiet otherwise. --}}
<div x-show="selected.length > 0" x-cloak class="flex items-center gap-2">
    <span class="text-sm text-gray-500">
        <span class="font-medium text-gray-800" x-text="selected.length"></span> dipilih
    </span>

    <x-dropdown align="right" width="w-48">
        <x-slot:trigger>
            <x-button type="button" size="sm" variant="secondary" icon-right="chevron-down" loading="bulking">
                Aksi massal
            </x-button>
        </x-slot:trigger>

        @can('news.publish')
            <x-dropdown.item icon="check-circle" @click="bulk('publish')">Terbitkan</x-dropdown.item>
        @endcan

        @can('news.update')
            <x-dropdown.item icon="archive-box" @click="bulk('draft')">Jadikan draf</x-dropdown.item>
        @endcan

        @can('news.delete')
            <x-dropdown.divider />
            <x-dropdown.item icon="trash" variant="danger" @click="confirmingBulkDelete = true">
                Hapus
            </x-dropdown.item>
        @endcan
    </x-dropdown>
</div>

<x-modal.confirm show="confirmingBulkDelete"
                 title="Hapus berita terpilih?"
                 loading="bulking"
                 on-confirm="bulk('delete').then(() => confirmingBulkDelete = false)">
    <span x-text="selected.length"></span> berita akan dipindahkan ke tempat sampah
    dan masih dapat dipulihkan.
</x-modal.confirm>
