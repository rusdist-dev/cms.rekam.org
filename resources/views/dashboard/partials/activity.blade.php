<x-card title="Aktivitas Terakhir" icon="clock" class="lg:col-span-2" :padding="false">
    <div x-data="apiResource('{{ route('dash-api.stats.show', 'recent_activity') }}', [])">

        {{-- loading --}}
        <div x-show="loading" x-cloak class="divide-y divide-gray-100">
            @for ($i = 0; $i < 5; $i++)
                <div class="flex items-center gap-3 px-4 py-3 sm:px-6">
                    <div class="skeleton h-8 w-8 rounded-full"></div>
                    <div class="flex-1 space-y-2">
                        <div class="skeleton h-3.5 w-2/5"></div>
                        <div class="skeleton h-3 w-1/4"></div>
                    </div>
                </div>
            @endfor
        </div>

        {{-- error --}}
        <div x-show="error" x-cloak class="px-4 py-8 sm:px-6">
            <x-empty-state title="Gagal memuat aktivitas" icon="exclamation-triangle" size="sm">
                <p class="text-sm text-gray-500" x-text="error"></p>
                <x-button size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
            </x-empty-state>
        </div>

        {{-- empty --}}
        <div x-show="isEmpty" x-cloak>
            <x-empty-state title="Belum ada aktivitas" description="Perubahan konten akan tercatat di sini." icon="list-bullet" size="sm" />
        </div>

        {{-- ready --}}
        <ul x-show="isReady" x-cloak role="list" class="divide-y divide-gray-100">
            <template x-for="item in data" :key="item.id">
                <li class="flex items-start gap-3 px-4 py-3 sm:px-6">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                        <x-icon name="pencil-square" class="h-4 w-4" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-gray-800">
                            <span class="font-medium" x-text="item.causer"></span>
                            <span x-text="' · ' + item.description.toLowerCase()"></span>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400" x-text="item.subject_type + ' · ' + item.created_at"></p>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</x-card>
