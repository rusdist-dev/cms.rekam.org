<div x-data="{ revealed: false, rotating: false, confirmingRotate: false }">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="API Key Company" icon="key"
                subtitle="Dipakai website company profile untuk membaca konten yang sudah terbit."
                class="lg:col-span-2">

            <div class="space-y-5">
                <div>
                    <label for="api-key" class="mb-1.5 block text-sm font-medium text-gray-700">Kunci Aktif</label>

                    <div class="flex gap-2">
                        <input id="api-key" readonly
                               :type="revealed ? 'text' : 'password'"
                               value="rk_live_prototipe_belum_dibuat"
                               class="block w-full rounded border-gray-300 bg-gray-50 py-2 font-mono text-sm text-gray-700 shadow-sm">

                        <x-button type="button" variant="secondary" @click="revealed = ! revealed"
                                  ::aria-label="revealed ? 'Sembunyikan kunci' : 'Tampilkan kunci'">
                            <span x-show="! revealed"><x-icon name="eye" class="h-4 w-4" /></span>
                            <span x-show="revealed" x-cloak><x-icon name="eye-slash" class="h-4 w-4" /></span>
                        </x-button>
                    </div>

                    <p class="mt-1.5 text-xs text-gray-500">
                        Kirim sebagai header <code class="rounded bg-gray-100 px-1 py-0.5 font-mono">X-Api-Key</code>
                        pada setiap permintaan ke API publik.
                    </p>
                </div>

                <x-alert variant="warning" title="Memutar kunci memutus akses">
                    Website company profile akan berhenti memuat konten sampai kunci baru dipasang di sana.
                </x-alert>
            </div>

            <x-slot:footer>
                <div class="flex justify-end">
                    <x-button type="button" variant="danger" icon="arrow-path" @click="confirmingRotate = true">
                        Putar Kunci
                    </x-button>
                </div>
            </x-slot:footer>
        </x-card>

        <x-card title="Cakupan Akses" icon="shield-check">
            <ul class="space-y-3 text-sm text-gray-600">
                <li class="flex items-start gap-2">
                    <x-icon name="check-circle" variant="solid" class="mt-0.5 h-4 w-4 shrink-0 text-success-500" />
                    Hanya membaca konten berstatus terbit.
                </li>
                <li class="flex items-start gap-2">
                    <x-icon name="check-circle" variant="solid" class="mt-0.5 h-4 w-4 shrink-0 text-success-500" />
                    Terbatas pada company ini saja.
                </li>
                <li class="flex items-start gap-2">
                    <x-icon name="x-circle" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                    Tidak bisa membuat, mengubah, atau menghapus konten.
                </li>
                <li class="flex items-start gap-2">
                    <x-icon name="x-circle" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                    Tidak bisa membaca data pengguna atau pesan kontak.
                </li>
            </ul>
        </x-card>
    </div>

    <x-modal.confirm show="confirmingRotate"
                     title="Putar API key sekarang?"
                     confirm-label="Putar Kunci"
                     loading="rotating"
                     on-confirm="rotating = true; setTimeout(() => { rotating = false; confirmingRotate = false }, 500)">
        Kunci lama langsung tidak berlaku. Pastikan Anda bisa memperbarui kunci di website
        company profile segera setelah ini.
    </x-modal.confirm>
</div>
