<x-card title="Klasifikasi" icon="tag">
    <div class="space-y-5">
        {{-- Categories are a relational table with its own endpoint (Fase 3),
             not a site_settings list — unlike programs below. --}}
        <x-form.field name="category_id" label="Kategori" required alpine>
            <x-form.remote-select
                name="category_id"
                :endpoint="route('dash-api.news-categories.index')"
                model="form.category_id"
                value-key="id"
                label-key="name.{{ config('cms.default_locale') }}"
                placeholder="Pilih kategori…"
                empty-text="Belum ada kategori. Tambahkan lebih dulu di Pengaturan."
                error-text="Gagal memuat kategori."
                alpine />
        </x-form.field>

        @feature('news_programs')
            {{-- Programs live in site_settings, so options differ per company
                 without a schema change (context.md §5.12). --}}
            <div x-data="apiResource('{{ route('dash-api.taxonomy.show', 'news_programs') }}', [])">
                <x-form.field name="related_programs" label="Program Terkait"
                              hint="Boleh lebih dari satu." alpine>
                    <div x-show="loading" x-cloak class="skeleton h-10 w-full rounded"></div>

                    <div x-show="! loading && ! error">
                        <x-form.multi-select name="related_programs"
                                             options-bind="data"
                                             model="form.related_programs"
                                             placeholder="Pilih program…" />
                    </div>

                    <p x-show="error" x-cloak role="alert" class="text-xs text-danger-600">
                        Gagal memuat program.
                        <button type="button" @click="load()" class="underline">Coba lagi</button>
                    </p>
                </x-form.field>
            </div>
        @endfeature
    </div>
</x-card>
