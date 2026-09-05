<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="contentForm('{{ route('dash-api.news.index') }}', @js(\App\Support\FormDefaults::news()), {
              recordId: {{ $recordId ? (int) $recordId : 'null' }},
              redirectTo: '{{ route('news.index') }}',
          })"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :breadcrumbs="[]" :back="route('news.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('news.index')">Batal</x-button>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="Konten">
                    <x-form.lang-tabs completeness="{ id: !!form.title.id, en: !!form.title.en }">
                        @foreach (config('cms.locales') as $locale)
                            <x-form.lang-panel :locale="$locale">
                                <x-form.field name="title.{{ $locale }}" label="Judul" :required="$locale === 'id'" alpine>
                                    <x-form.input :id="'title.'.$locale" name="title.{{ $locale }}" alpine
                                                  x-model="form.title.{{ $locale }}"
                                                  placeholder="Judul berita" />
                                </x-form.field>

                                <x-form.field name="slug.{{ $locale }}" label="Slug"
                                              hint="Dibuat otomatis dari judul, boleh diubah." alpine>
                                    <x-form.input :id="'slug.'.$locale" name="slug.{{ $locale }}" alpine
                                                  x-model="form.slug.{{ $locale }}"
                                                  placeholder="judul-berita" />
                                </x-form.field>

                                <x-form.field name="excerpt.{{ $locale }}" label="Ringkasan"
                                              hint="Tampil di kartu daftar dan pratinjau berbagi." alpine>
                                    <x-form.textarea :id="'excerpt.'.$locale" name="excerpt.{{ $locale }}" alpine
                                                     rows="3" x-model="form.excerpt.{{ $locale }}" />
                                </x-form.field>

                                <x-form.field name="body.{{ $locale }}" label="Isi Berita" :required="$locale === 'id'" alpine>
                                    <x-form.editor :id="'body.'.$locale" model="form.body.{{ $locale }}" />
                                </x-form.field>
                            </x-form.lang-panel>
                        @endforeach
                    </x-form.lang-tabs>
                </x-card>

                @include('shared.seo-card')
            </div>

            <div class="space-y-6">
                @include('news.partials.publish-card')
                @include('news.partials.taxonomy-card')

                <x-card title="Gambar Sampul">
                    <x-form.image-upload name="cover" model="coverValue" />
                </x-card>
            </div>
        </div>
    </form>
</x-app-layout>
