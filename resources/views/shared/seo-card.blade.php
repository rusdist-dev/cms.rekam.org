<x-card title="SEO" subtitle="Kosongkan untuk memakai judul dan ringkasan konten." icon="globe-alt">
    <x-form.lang-tabs>
        @foreach (config('cms.locales') as $locale)
            <x-form.lang-panel :locale="$locale">
                <x-form.field name="meta_title.{{ $locale }}" label="Meta Title"
                              hint="Idealnya di bawah 60 karakter." alpine>
                    <x-form.input :id="'meta_title.'.$locale" name="meta_title.{{ $locale }}" alpine
                                  x-model="form.meta_title.{{ $locale }}" />
                </x-form.field>

                <x-form.field name="meta_description.{{ $locale }}" label="Meta Description"
                              hint="Idealnya di bawah 160 karakter." alpine>
                    <x-form.textarea :id="'meta_description.'.$locale" name="meta_description.{{ $locale }}" alpine
                                     rows="3" x-model="form.meta_description.{{ $locale }}" />
                </x-form.field>
            </x-form.lang-panel>
        @endforeach
    </x-form.lang-tabs>
</x-card>
