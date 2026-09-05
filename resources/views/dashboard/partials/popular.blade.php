<x-card title="Berita Terpopuler" icon="arrow-trending-up" :padding="false">
    <div x-data="apiResource('{{ route('dash-api.stats.show', 'popular_news') }}', [])">

        <div x-show="loading" x-cloak class="divide-y divide-gray-100">
            @for ($i = 0; $i < 5; $i++)
                <div class="space-y-2 px-4 py-3 sm:px-6">
                    <div class="skeleton h-3.5 w-4/5"></div>
                    <div class="skeleton h-3 w-1/5"></div>
                </div>
            @endfor
        </div>

        <div x-show="error" x-cloak class="px-4 py-8 sm:px-6">
            <x-empty-state title="Gagal memuat data" icon="exclamation-triangle" size="sm">
                <x-button size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
            </x-empty-state>
        </div>

        <div x-show="isEmpty" x-cloak>
            <x-empty-state title="Belum ada data kunjungan" icon="chart-bar" size="sm" />
        </div>

        <ol x-show="isReady" x-cloak class="divide-y divide-gray-100">
            <template x-for="(item, index) in data" :key="item.id">
                <li class="flex items-start gap-3 px-4 py-3 sm:px-6">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500"
                          x-text="index + 1"></span>

                    <div class="min-w-0 flex-1">
                        <a :href="'{{ route('news.index') }}'"
                           class="block truncate text-sm font-medium text-gray-800 transition hover:text-primary-600"
                           x-text="item.title.id"></a>
                        <p class="mt-0.5 text-xs text-gray-400">
                            <span x-text="item.views.toLocaleString('id-ID')"></span> kunjungan
                        </p>
                    </div>
                </li>
            </template>
        </ol>
    </div>
</x-card>
