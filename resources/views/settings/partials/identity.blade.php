<form x-data="resourceForm('{{ route('dash-api.taxonomy.show', 'site_identity') }}', {
          name: '', tagline: { id: '', en: '' }, logo: null, favicon: null,
      })"
      @submit.prevent="submit()">

    @include('shared.form-states')

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="Identitas Situs" class="lg:col-span-2">
            <div class="space-y-5">
                <x-form.field name="name" label="Nama Situs" required alpine>
                    <x-form.input id="name" name="name" alpine x-model="form.name" />
                </x-form.field>

                <x-form.lang-tabs completeness="{ id: !!form.tagline.id, en: !!form.tagline.en }">
                    @foreach (config('cms.locales') as $locale)
                        <x-form.lang-panel :locale="$locale">
                            <x-form.field name="tagline.{{ $locale }}" label="Tagline" :required="$locale === 'id'" alpine>
                                <x-form.input :id="'tagline.'.$locale" name="tagline.{{ $locale }}" alpine
                                              x-model="form.tagline.{{ $locale }}" />
                            </x-form.field>
                        </x-form.lang-panel>
                    @endforeach
                </x-form.lang-tabs>
            </div>

            <x-slot:footer>
                <div class="flex justify-end">
                    <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
                </div>
            </x-slot:footer>
        </x-card>

        <div class="space-y-6">
            <x-card title="Logo">
                <x-form.image-upload name="logo" model="form.logo" ratio="aspect-[3/2]" />
            </x-card>

            <x-card title="Favicon">
                <x-form.image-upload name="favicon" model="form.favicon" ratio="aspect-square" />
            </x-card>
        </div>
    </div>
</form>
