<x-card title="Kategori Berita" icon="newspaper" :padding="false">
    <div x-data="newsCategoryEditor('{{ route('dash-api.news-categories.index') }}')">

        {{-- loading --}}
        <div x-show="loading" x-cloak class="space-y-2 p-4 sm:p-6">
            @for ($i = 0; $i < 3; $i++)
                <div class="skeleton h-10 w-full rounded"></div>
            @endfor
        </div>

        <div x-show="! loading" x-cloak class="p-4 sm:p-6">
            <p x-show="error" x-cloak role="alert" class="mb-3 text-sm text-danger-600" x-text="error"></p>

            <template x-if="items.length === 0">
                <p class="text-sm text-gray-500">Belum ada kategori.</p>
            </template>

            <ul class="divide-y divide-gray-100">
                <template x-for="item in items" :key="item.id">
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-800" x-text="item.name.id"></p>
                            <p class="text-xs text-gray-400">
                                <span x-text="item.news_count"></span> berita
                            </p>
                        </div>

                        <button type="button"
                                :disabled="deletingId === item.id"
                                @click="remove(item.id)"
                                class="rounded p-1.5 text-gray-400 transition hover:bg-danger-50 hover:text-danger-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 disabled:opacity-50"
                                :aria-label="'Hapus ' + item.name.id">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </li>
                </template>
            </ul>

            <form @submit.prevent="create()" class="mt-4 flex gap-2">
                <label for="news-category-new" class="sr-only">Nama kategori baru</label>
                <input type="text" id="news-category-new" x-model="newName"
                       placeholder="Nama kategori baru…"
                       class="block w-full rounded border-gray-300 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">

                <x-button type="submit" size="sm" icon="plus" loading="creating">Tambah</x-button>
            </form>
        </div>
    </div>
</x-card>
