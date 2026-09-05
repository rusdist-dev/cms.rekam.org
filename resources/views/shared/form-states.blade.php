{{-- Shared loading / error / saved banners for every fetch-driven form
     (context.md §2.3). Field-level 422 messages render inline at the field. --}}

<div x-show="loading" x-cloak class="mb-6">
    <div class="flex items-center gap-3 rounded-card border border-gray-200 bg-white px-4 py-3 shadow-card">
        <x-spinner class="text-primary-500" />
        <span class="text-sm text-gray-500">Memuat data…</span>
    </div>
</div>

<div x-show="error" x-cloak class="mb-6">
    <x-alert variant="danger" title="Terjadi kesalahan">
        <span x-text="error"></span>
        <x-slot:actions>
            <x-button size="sm" variant="secondary" icon="arrow-path" @click="isEditing ? load() : (error = null)">
                Coba lagi
            </x-button>
        </x-slot:actions>
    </x-alert>
</div>

<div x-show="hasErrors" x-cloak class="mb-6">
    <x-alert variant="danger" title="Periksa kembali isian yang ditandai">
        Beberapa isian belum sesuai. Bagian yang bermasalah ditandai merah di bawah.
    </x-alert>
</div>
