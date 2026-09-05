<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="resourceForm('{{ route('dash-api.units.index') }}', @js(\App\Support\FormDefaults::unit()), {
              recordId: {{ $recordId ? (int) $recordId : 'null' }},
              redirectTo: '{{ route('units.index') }}',
          })"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :back="route('units.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('units.index')">Batal</x-button>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Data Unit" class="lg:col-span-2">
                <div class="space-y-5">
                    <x-form.field name="name" label="Nama Unit" required alpine>
                        <x-form.input id="name" name="name" alpine x-model="form.name" />
                    </x-form.field>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-form.field name="domain" label="Domain" required
                                      hint="Tanpa https://, contoh: perikanan.org" alpine>
                            <x-form.input id="domain" name="domain" alpine
                                          icon="globe-alt" x-model="form.domain"
                                          placeholder="perikanan.org" />
                        </x-form.field>

                        {{-- plan.md §5.5 q6: url and domain are kept separate until
                             confirmed that url is always https://{domain}. --}}
                        <x-form.field name="url" label="Tautan Tujuan" required
                                      hint="Boleh berbeda dari domain yang ditampilkan." alpine>
                            <x-form.input id="url" name="url" type="url" alpine
                                          icon="link" x-model="form.url"
                                          placeholder="https://perikanan.org" />
                        </x-form.field>
                    </div>

                    <x-form.lang-tabs completeness="{ id: !!form.description.id, en: !!form.description.en }">
                        @foreach (config('cms.locales') as $locale)
                            <x-form.lang-panel :locale="$locale">
                                <x-form.field name="description.{{ $locale }}" label="Deskripsi" :required="$locale === 'id'" alpine>
                                    <x-form.textarea :id="'description.'.$locale" name="description.{{ $locale }}" alpine
                                                     rows="4" x-model="form.description.{{ $locale }}" />
                                </x-form.field>
                            </x-form.lang-panel>
                        @endforeach
                    </x-form.lang-tabs>
                </div>
            </x-card>

            <div class="space-y-6">
                <x-card title="Logo">
                    <x-form.image-upload name="logo" model="form.logo" ratio="aspect-square" />
                </x-card>

                <x-card title="Tampilan">
                    <x-form.toggle name="is_active" model="form.is_active"
                                   label="Tampilkan di website"
                                   hint="Matikan untuk menyembunyikan tanpa menghapus." />
                </x-card>
            </div>
        </div>
    </form>
</x-app-layout>
