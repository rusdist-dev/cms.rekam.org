<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    {{-- Contact details live in site_settings, so they are edited here and read
         by the compro through the public settings endpoint (plan.md §2.5). --}}
    <form x-data="settingsForm('{{ route('dash-api.contacts.settings.show') }}', {
              email: '', phone: '', whatsapp: '', address: { id: '', en: '' }, map_embed: '',
          })"
          @submit.prevent="submit()">

        <x-page-header title="Informasi Kontak"
                       subtitle="Ditampilkan di halaman kontak website company."
                       :back="route('contacts.index')">
            <x-slot:actions>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Kontak Utama" icon="phone" class="lg:col-span-2">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-form.field name="email" label="Email" required alpine>
                        <x-form.input id="email" name="email" type="email" alpine
                                      icon="at-symbol" x-model="form.email" />
                    </x-form.field>

                    <x-form.field name="phone" label="Telepon" alpine>
                        <x-form.input id="phone" name="phone" alpine
                                      icon="phone" x-model="form.phone" />
                    </x-form.field>

                    <x-form.field name="whatsapp" label="WhatsApp"
                                  hint="Format internasional, contoh: 6281234567890." alpine
                                  class="sm:col-span-2">
                        <x-form.input id="whatsapp" name="whatsapp" alpine x-model="form.whatsapp" />
                    </x-form.field>
                </div>

                <div class="mt-5">
                    <x-form.lang-tabs>
                        @foreach (config('cms.locales') as $locale)
                            <x-form.lang-panel :locale="$locale">
                                <x-form.field name="address.{{ $locale }}" label="Alamat" :required="$locale === 'id'" alpine>
                                    <x-form.textarea :id="'address.'.$locale" name="address.{{ $locale }}" alpine
                                                     rows="3" x-model="form.address.{{ $locale }}" />
                                </x-form.field>
                            </x-form.lang-panel>
                        @endforeach
                    </x-form.lang-tabs>
                </div>
            </x-card>

            <x-card title="Peta" icon="map-pin">
                <x-form.field name="map_embed" label="Kode Sematan Peta"
                              hint="Tempel kode embed dari Google Maps." alpine>
                    <x-form.textarea id="map_embed" name="map_embed" alpine
                                     rows="6" x-model="form.map_embed"
                                     placeholder="&lt;iframe src=&quot;https://www.google.com/maps/embed?…&quot;&gt;&lt;/iframe&gt;" />
                </x-form.field>
            </x-card>
        </div>
    </form>
</x-app-layout>
