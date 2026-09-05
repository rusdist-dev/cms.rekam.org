<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="resourceForm('{{ route('dash-api.partners.index') }}', @js(\App\Support\FormDefaults::partner()), {
              recordId: {{ $recordId ? (int) $recordId : 'null' }},
              redirectTo: '{{ route('partners.index') }}',
          })"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :back="route('partners.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('partners.index')">Batal</x-button>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Data Partner" class="lg:col-span-2">
                <div class="space-y-5">
                    <x-form.field name="name" label="Nama Partner" required alpine>
                        <x-form.input id="name" name="name" alpine x-model="form.name" />
                    </x-form.field>

                    <x-form.field name="url" label="Tautan Situs" alpine>
                        <x-form.input id="url" name="url" type="url" alpine
                                      icon="link" x-model="form.url" placeholder="https://…" />
                    </x-form.field>

                    <x-form.lang-tabs completeness="{ id: !!form.title.id, en: !!form.title.en }">
                        @foreach (config('cms.locales') as $locale)
                            <x-form.lang-panel :locale="$locale">
                                <x-form.field name="title.{{ $locale }}" label="Keterangan" :required="$locale === 'id'"
                                              hint="Contoh: Mitra Strategis, Donor." alpine>
                                    <x-form.input :id="'title.'.$locale" name="title.{{ $locale }}" alpine
                                                  x-model="form.title.{{ $locale }}" />
                                </x-form.field>
                            </x-form.lang-panel>
                        @endforeach
                    </x-form.lang-tabs>
                </div>
            </x-card>

            <div class="space-y-6">
                <x-card title="Logo">
                    <x-form.image-upload name="logo" model="form.logo" ratio="aspect-[3/2]" />
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
