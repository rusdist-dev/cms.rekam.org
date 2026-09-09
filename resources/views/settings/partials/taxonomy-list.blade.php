{{-- x-data wraps the whole card, not just its body: x-slot:footer below is
     extracted and rendered by <x-card> in a sibling div outside the body slot,
     so the footer's Save button needs the Alpine scope to reach that far. --}}
<div x-data="taxonomyEditor(
        '{{ route('dash-api.taxonomy.edit', $group) }}',
        '{{ route('dash-api.taxonomy.update', $group) }}'
    )">
    <x-card :title="$listTitle" :subtitle="$listHint" :icon="$listIcon" :padding="false">

        {{-- loading --}}
        <div x-show="loading" x-cloak class="space-y-2 p-4 sm:p-6">
            @for ($i = 0; $i < 3; $i++)
                <div class="skeleton h-10 w-full rounded"></div>
            @endfor
        </div>

        {{-- error --}}
        <div x-show="error" x-cloak class="p-4 sm:p-6">
            <x-alert variant="danger">
                <span x-text="error"></span>
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
                </x-slot:actions>
            </x-alert>
        </div>

        {{-- empty + ready share the repeater, which renders its own empty state --}}
        <div x-show="! loading && ! error" x-cloak class="p-4 sm:p-6">
            <x-form.repeater model="options"
                             :blank="['slug' => '', 'label' => ['id' => '', 'en' => '']]"
                             add-label="Tambah pilihan"
                             empty-title="Belum ada pilihan"
                             empty-description="Tambahkan minimal satu pilihan agar bisa dipakai di formulir konten.">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label :for="'{{ $group }}-slug-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                            Slug
                        </label>
                        <input type="text"
                               :id="'{{ $group }}-slug-' + index"
                               x-model="row.slug"
                               placeholder="blue-carbon"
                               class="block w-full rounded border-gray-300 py-1.5 font-mono text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <p class="mt-1 text-xs text-gray-400">
                            Nilai yang disimpan di konten. Mengubahnya memutus kaitan dengan konten lama.
                        </p>
                    </div>

                    <div>
                        <label :for="'{{ $group }}-label-id-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                            Label (Indonesia)
                        </label>
                        <input type="text"
                               :id="'{{ $group }}-label-id-' + index"
                               x-model="row.label.id"
                               placeholder="Blue Carbon"
                               class="block w-full rounded border-gray-300 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>

                    <div>
                        <label :for="'{{ $group }}-label-en-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                            Label (Inggris)
                        </label>
                        <input type="text"
                               :id="'{{ $group }}-label-en-' + index"
                               x-model="row.label.en"
                               placeholder="Blue Carbon"
                               class="block w-full rounded border-gray-300 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                </div>
            </x-form.repeater>

            <div x-show="saveError" x-cloak class="mt-4">
                <x-alert variant="danger">
                    <span x-text="saveError"></span>
                </x-alert>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-gray-500">Urutan baris menentukan urutan tampil.</p>
                <div class="flex items-center gap-3">
                    <span x-show="saved" x-cloak class="text-xs text-success-600">Tersimpan.</span>
                    <x-button type="button" size="sm" icon="check" loading="saving" @click="save()">
                        Simpan
                    </x-button>
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</div>
