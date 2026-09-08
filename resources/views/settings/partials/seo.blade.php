<form x-data="settingsForm('{{ route('dash-api.settings.seo.show') }}', {
          meta_title: { id: '', en: '' }, meta_description: { id: '', en: '' },
          og_image: null, keywords: [],
      })"
      @submit.prevent="submit()">

    @include('shared.form-states')

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="SEO Bawaan" subtitle="Dipakai bila konten tidak punya meta sendiri." class="lg:col-span-2">
            <x-form.lang-tabs>
                @foreach (config('cms.locales') as $locale)
                    <x-form.lang-panel :locale="$locale">
                        <x-form.field name="meta_title.{{ $locale }}" label="Meta Title" alpine>
                            <x-form.input :id="'meta_title.'.$locale" name="meta_title.{{ $locale }}" alpine
                                          x-model="form.meta_title.{{ $locale }}" />
                        </x-form.field>

                        <x-form.field name="meta_description.{{ $locale }}" label="Meta Description" alpine>
                            <x-form.textarea :id="'meta_description.'.$locale" name="meta_description.{{ $locale }}" alpine
                                             rows="3" x-model="form.meta_description.{{ $locale }}" />
                        </x-form.field>
                    </x-form.lang-panel>
                @endforeach
            </x-form.lang-tabs>

            <div class="mt-5">
                <x-form.field name="keywords" label="Kata Kunci"
                              hint="Tekan Enter setelah tiap kata kunci." alpine>
                    <x-form.tag-input name="keywords" model="form.keywords" />
                </x-form.field>
            </div>

            <x-slot:footer>
                <div class="flex justify-end">
                    <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
                </div>
            </x-slot:footer>
        </x-card>

        <x-card title="Gambar Berbagi (OG Image)">
            <x-form.image-upload name="og_image" model="form.og_image" />
        </x-card>
    </div>
</form>
