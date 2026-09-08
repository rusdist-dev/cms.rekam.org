<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- The record fetch plus archive()/destroy() live in
         resources/js/alpine/contactDetail.js — see that file and
         resources/js/extend.js for why this must be a direct function call,
         not `{ ...apiResource(...), confirming }`. --}}
    <div x-data="contactDetail(
            '{{ route('dash-api.contacts.show', $recordId) }}',
            '{{ route('dash-api.contacts.archive', $recordId) }}',
            '{{ route('dash-api.contacts.destroy', $recordId) }}',
            '{{ route('contacts.index') }}',
         )">

        <x-page-header title="Detail Pesan" :back="route('contacts.index')">
            <x-slot:actions>
                {{-- Replies go out through the reader's own mail client
                     (plan.md §2.5) — the CMS never sends mail on their behalf. --}}
                <x-button variant="secondary" icon="archive-box" x-show="isReady && data?.status !== 'archived'" x-cloak
                          loading="archiving" @click="archive()">
                    Arsipkan
                </x-button>

                <x-button icon="paper-airplane" x-show="isReady" x-cloak
                          ::href="'mailto:' + (data?.email ?? '') + '?subject=' + encodeURIComponent('Re: ' + (data?.subject ?? ''))">
                    Balas via Email
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- loading --}}
        <div x-show="loading" x-cloak class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="space-y-3 rounded-card border border-gray-200 bg-white p-6">
                    <div class="skeleton h-5 w-1/2"></div>
                    <div class="skeleton h-4 w-full"></div>
                    <div class="skeleton h-4 w-full"></div>
                    <div class="skeleton h-4 w-2/3"></div>
                </div>
            </div>
            <div class="space-y-3 rounded-card border border-gray-200 bg-white p-6">
                <div class="skeleton h-4 w-2/3"></div>
                <div class="skeleton h-4 w-1/2"></div>
            </div>
        </div>

        {{-- error --}}
        <div x-show="error" x-cloak>
            <x-alert variant="danger" title="Gagal memuat pesan">
                <span x-text="error"></span>
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
                </x-slot:actions>
            </x-alert>
        </div>

        {{-- empty --}}
        <div x-show="isEmpty" x-cloak>
            <x-card>
                <x-empty-state title="Pesan tidak ditemukan"
                               description="Pesan mungkin sudah dihapus."
                               icon="inbox">
                    <x-button :href="route('contacts.index')" size="sm" variant="secondary">
                        Kembali ke kotak masuk
                    </x-button>
                </x-empty-state>
            </x-card>
        </div>

        {{-- ready --}}
        <div x-show="isReady" x-cloak class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card class="lg:col-span-2">
                <x-slot:title><span x-text="data?.subject"></span></x-slot:title>

                <article class="prose prose-sm max-w-none whitespace-pre-line text-gray-700"
                         x-text="data?.message"></article>
            </x-card>

            <div class="space-y-6">
                <x-card title="Pengirim" icon="user-circle">
                    <dl class="space-y-4 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Nama</dt>
                            <dd class="mt-0.5 text-gray-800" x-text="data?.name"></dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Email</dt>
                            <dd class="mt-0.5">
                                <a :href="'mailto:' + (data?.email ?? '')"
                                   class="text-primary-600 hover:underline" x-text="data?.email"></a>
                            </dd>
                        </div>

                        <div x-show="data?.phone">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Telepon</dt>
                            <dd class="mt-0.5">
                                <a :href="'tel:' + (data?.phone ?? '')"
                                   class="text-primary-600 hover:underline" x-text="data?.phone"></a>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Diterima</dt>
                            <dd class="mt-0.5 text-gray-800" x-text="data?.created_at"></dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Alamat IP</dt>
                            <dd class="mt-0.5 font-mono text-xs text-gray-500" x-text="data?.ip"></dd>
                        </div>
                    </dl>
                </x-card>

                <x-card title="Tindakan">
                    <x-button type="button" variant="ghost-danger" icon="trash" block
                              @click="confirming = true">
                        Hapus Pesan
                    </x-button>
                </x-card>
            </div>
        </div>

        <x-modal.confirm show="confirming"
                         on-close="confirming = false"
                         title="Hapus pesan ini?"
                         confirm-label="Hapus"
                         loading="deleting"
                         on-confirm="destroy()">
            Pesan akan dihapus permanen dan tidak bisa dipulihkan.
        </x-modal.confirm>
    </div>
</x-app-layout>
