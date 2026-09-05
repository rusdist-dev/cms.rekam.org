<x-card title="API Key" icon="key"
        subtitle="Dipakai website company profile untuk membaca konten terbit.">

    <div class="space-y-4">
        {{-- Only a hash is stored, so an existing key can never be re-displayed. --}}
        <div x-show="! apiKey" x-cloak>
            <template x-if="form.has_api_key">
                <p class="text-sm text-gray-600">
                    Kunci aktif dibuat <span class="font-medium" x-text="form.api_key_generated_at ?? '—'"></span>.
                    Nilainya tidak dapat ditampilkan lagi — hanya hash-nya yang disimpan.
                </p>
            </template>

            <template x-if="! form.has_api_key">
                <p class="text-sm text-warning-700">Company ini belum punya API key.</p>
            </template>
        </div>

        {{-- Shown once, right after rotation. --}}
        <div x-show="apiKey" x-cloak>
            <x-alert variant="warning" title="Simpan kunci ini sekarang">
                Kunci hanya ditampilkan sekali dan tidak dapat dilihat kembali.
            </x-alert>

            <div class="mt-3 flex gap-2">
                <input readonly :value="apiKey" x-ref="key"
                       class="block w-full rounded border-gray-300 bg-gray-50 py-2 font-mono text-xs text-gray-800 shadow-sm"
                       aria-label="API key baru">

                <x-button type="button" variant="secondary" @click="$refs.key.select()" aria-label="Pilih kunci">
                    <x-icon name="clipboard-document-list" class="h-4 w-4" />
                </x-button>
            </div>

            <button type="button" @click="dismissKey()"
                    class="mt-2 rounded text-xs text-gray-500 underline transition hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                Saya sudah menyimpannya
            </button>
        </div>

        <p x-show="rotateError" x-cloak x-text="rotateError" role="alert" class="text-sm text-danger-600"></p>

        <p class="text-xs text-gray-500">
            Dikirim sebagai header <code class="rounded bg-gray-100 px-1 py-0.5 font-mono">X-Api-Key</code>
            pada setiap permintaan ke API publik.
        </p>
    </div>

    <x-slot:footer>
        <div class="flex justify-end">
            @can('tenants.update')
                <x-button type="button" variant="danger" icon="arrow-path" @click="confirmingRotate = true">
                    Putar Kunci
                </x-button>
            @endcan
        </div>
    </x-slot:footer>

    <x-modal.confirm show="confirmingRotate"
                     title="Putar API key sekarang?"
                     confirm-label="Putar Kunci"
                     loading="rotating"
                     on-confirm="rotate()">
        Kunci lama langsung tidak berlaku dan website company profile akan berhenti
        memuat konten sampai kunci baru dipasang di sana.
    </x-modal.confirm>
</x-card>
