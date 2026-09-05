<x-card title="Izin" subtitle="Centang tindakan yang boleh dilakukan peran ini." :padding="false">

    {{-- loading --}}
    <div x-show="matrixLoading" x-cloak class="space-y-2 p-4 sm:p-6">
        @for ($i = 0; $i < 5; $i++)
            <div class="skeleton h-9 w-full rounded"></div>
        @endfor
    </div>

    {{-- error --}}
    <div x-show="matrixError" x-cloak class="p-4 sm:p-6">
        <x-alert variant="danger" title="Gagal memuat daftar izin">
            <span x-text="matrixError"></span>
            <x-slot:actions>
                <x-button size="sm" variant="secondary" icon="arrow-path" @click="loadMatrix()">Coba lagi</x-button>
            </x-slot:actions>
        </x-alert>
    </div>

    {{-- empty --}}
    <div x-show="! matrixLoading && ! matrixError && modules.length === 0" x-cloak>
        <x-empty-state title="Belum ada izin terdaftar"
                       description="Jalankan php artisan db:seed untuk membuat daftar izin."
                       icon="shield-check" size="sm" />
    </div>

    {{-- ready --}}
    <div x-show="! matrixLoading && ! matrixError && modules.length > 0" x-cloak class="divide-y divide-gray-100">
        <template x-for="module in modules" :key="module.module">
            <div class="px-4 py-4 sm:px-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800" x-text="module.label"></p>
                        <p class="font-mono text-xs text-gray-400" x-text="module.module"></p>
                    </div>

                    <button type="button" @click="toggleModule(module)"
                            class="shrink-0 rounded text-xs font-medium text-primary-600 transition hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            x-text="moduleState(module) === 'all' ? 'Kosongkan semua' : 'Pilih semua'"
                            :aria-label="(moduleState(module) === 'all' ? 'Kosongkan semua izin ' : 'Pilih semua izin ') + module.label"></button>
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <template x-for="action in module.actions" :key="action.name">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox"
                                   :checked="has(action.name)"
                                   @change="toggle(action.name)"
                                   :aria-label="action.label + ' ' + module.label"
                                   class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="text-gray-700" x-text="action.label"></span>
                        </label>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <x-slot:footer>
        <p class="text-xs text-gray-500">
            <span class="font-medium text-gray-700" x-text="grantedCount"></span> izin dipilih.
            Modul yang tidak aktif untuk sebuah company tetap tidak dapat diakses meski izinnya dicentang.
        </p>
    </x-slot:footer>
</x-card>
