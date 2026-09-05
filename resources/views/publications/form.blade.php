<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="resourceForm('{{ route('dash-api.publications.index') }}', @js(\App\Support\FormDefaults::publication()), {
              recordId: {{ $recordId ? (int) $recordId : 'null' }},
              redirectTo: '{{ route('publications.index') }}',
          })"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :back="route('publications.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('publications.index')">Batal</x-button>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Konten" class="lg:col-span-2">
                <x-form.lang-tabs completeness="{ id: !!form.title.id, en: !!form.title.en }">
                    @foreach (config('cms.locales') as $locale)
                        <x-form.lang-panel :locale="$locale">
                            <x-form.field name="title.{{ $locale }}" label="Judul" :required="$locale === 'id'" alpine>
                                <x-form.input :id="'title.'.$locale" name="title.{{ $locale }}" alpine
                                              x-model="form.title.{{ $locale }}" />
                            </x-form.field>

                            <x-form.field name="description.{{ $locale }}" label="Deskripsi" alpine>
                                <x-form.textarea :id="'description.'.$locale" name="description.{{ $locale }}" alpine
                                                 rows="5" x-model="form.description.{{ $locale }}" />
                            </x-form.field>
                        </x-form.lang-panel>
                    @endforeach
                </x-form.lang-tabs>
            </x-card>

            <div class="space-y-6">
                <x-card title="Klasifikasi" icon="tag">
                    <div class="space-y-5">
                        <div x-data="apiResource('{{ route('dash-api.taxonomy.show', 'publication_categories') }}', [])">
                            <x-form.field name="category" label="Kategori" required alpine>
                                <select id="category" x-model="form.category" :disabled="loading"
                                        :class="fieldError('category') ? 'border-danger-400' : 'border-gray-300'"
                                        class="block w-full rounded py-2 pe-9 ps-3 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-50">
                                    <option value="">Pilih kategori…</option>
                                    <template x-for="opt in (data ?? [])" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label"></option>
                                    </template>
                                </select>
                            </x-form.field>

                            <p x-show="error" x-cloak class="mt-1.5 text-xs text-danger-600">
                                Gagal memuat kategori.
                                <button type="button" @click="load()" class="underline">Coba lagi</button>
                            </p>
                        </div>

                        <x-form.toggle name="is_featured" model="form.is_featured"
                                       label="Jadikan unggulan"
                                       hint="Tampil lebih dulu di halaman publikasi." />
                    </div>
                </x-card>

                <x-card title="Berkas PDF">
                    <x-form.file-upload name="file" model="form.file" />
                </x-card>

                <x-card title="Gambar Sampul">
                    <x-form.image-upload name="cover" model="form.cover" ratio="aspect-[3/4]" />
                </x-card>
            </div>
        </div>
    </form>
</x-app-layout>
