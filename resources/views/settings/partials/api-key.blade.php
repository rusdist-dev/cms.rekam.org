@can('update', $currentTenant)
    {{-- Rotating breaks the public compro site until the new key is installed
         there, so this stays behind tenants.update (super-admin) even though
         the rest of Pengaturan Situs only needs settings.view/update — the
         other tabs' convention (show the button, let the server 403) does not
         apply to something this destructive. --}}
    <div x-data="apiResource('{{ route('dash-api.tenants.show', $currentTenant->id) }}', null, {
            extra: {
                rotating: false,
                confirmingRotate: false,
                revealedKey: null,

                async rotate() {
                    this.rotating = true

                    try {
                        const res = await window.api.post('{{ route('dash-api.tenants.api-key', $currentTenant->id) }}')
                        this.revealedKey = res.data.api_key
                        await this.load()
                    } catch (e) {
                        this.error = e.message
                    } finally {
                        this.rotating = false
                        this.confirmingRotate = false
                    }
                },
            },
         })">

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="API Key Company" icon="key"
                    subtitle="Dipakai website company profile untuk membaca konten yang sudah terbit."
                    class="lg:col-span-2">

                <div class="space-y-5">
                    <template x-if="revealedKey">
                        <div>
                            <label for="api-key-new" class="mb-1.5 block text-sm font-medium text-gray-700">
                                Kunci Baru
                            </label>
                            <input id="api-key-new" readonly :value="revealedKey"
                                   class="block w-full rounded border-gray-300 bg-gray-50 py-2 font-mono text-sm text-gray-700 shadow-sm">
                            <x-alert variant="warning" class="mt-3">
                                Kunci ini hanya ditampilkan sekali. Salin dan simpan sekarang.
                            </x-alert>
                        </div>
                    </template>

                    <template x-if="! revealedKey">
                        <p class="text-sm text-gray-600">
                            <template x-if="data?.has_api_key">
                                <span>Kunci aktif sejak <span class="font-medium" x-text="data.api_key_generated_at"></span>.</span>
                            </template>
                            <template x-if="! data?.has_api_key">
                                <span>Belum ada kunci untuk company ini.</span>
                            </template>
                        </p>
                    </template>

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
                         on-close="confirmingRotate = false"
                         title="Putar API key sekarang?"
                         confirm-label="Putar Kunci"
                         loading="rotating"
                         on-confirm="rotate()">
            Kunci lama langsung tidak berlaku. Pastikan Anda bisa memperbarui kunci di website
            company profile segera setelah ini.
        </x-modal.confirm>
    </div>
@else
    <x-alert variant="info">
        Hanya super admin yang bisa melihat dan memutar API key company ini.
    </x-alert>
@endcan
