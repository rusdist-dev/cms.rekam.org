<x-card :title="$listTitle" :subtitle="$listHint" :icon="$listIcon" :padding="false">
    {{-- Direct function call, not `{ ...apiResource(...), saving }` — see
         apiResource.js and resources/js/extend.js. --}}
    <div x-data="apiResource('{{ route('dash-api.taxonomy.show', $group) }}', [], {
            extra: { saving: false },
         })">

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
            <x-form.repeater model="data"
                             :blank="['value' => '', 'label' => '']"
                             add-label="Tambah pilihan"
                             empty-title="Belum ada pilihan"
                             empty-description="Tambahkan minimal satu pilihan agar bisa dipakai di formulir konten.">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :for="'{{ $group }}-slug-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                            Slug
                        </label>
                        <input type="text"
                               :id="'{{ $group }}-slug-' + index"
                               x-model="row.value"
                               placeholder="blue-carbon"
                               class="block w-full rounded border-gray-300 py-1.5 font-mono text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <p class="mt-1 text-xs text-gray-400">
                            Nilai yang disimpan di konten. Mengubahnya memutus kaitan dengan konten lama.
                        </p>
                    </div>

                    <div>
                        <label :for="'{{ $group }}-label-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                            Label
                        </label>
                        <input type="text"
                               :id="'{{ $group }}-label-' + index"
                               x-model="row.label"
                               placeholder="Blue Carbon"
                               class="block w-full rounded border-gray-300 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                </div>
            </x-form.repeater>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-gray-500">Urutan baris menentukan urutan tampil.</p>
                <x-button type="button" size="sm" icon="check" loading="saving"
                          @click="saving = true; setTimeout(() => saving = false, 500)">
                    Simpan
                </x-button>
            </div>
        </x-slot:footer>
    </div>
</x-card>
