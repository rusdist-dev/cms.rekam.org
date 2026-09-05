<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="resourceForm('{{ route('dash-api.milestones.index') }}', @js(\App\Support\FormDefaults::milestone()), {
              recordId: {{ $recordId ? (int) $recordId : 'null' }},
              redirectTo: '{{ route('milestones.index') }}',
          })"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :back="route('milestones.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('milestones.index')">Batal</x-button>
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

                            <x-form.field name="body.{{ $locale }}" label="Isi" :required="$locale === 'id'" alpine>
                                <x-form.textarea :id="'body.'.$locale" name="body.{{ $locale }}" alpine
                                                 rows="6" x-model="form.body.{{ $locale }}" />
                            </x-form.field>
                        </x-form.lang-panel>
                    @endforeach
                </x-form.lang-tabs>
            </x-card>

            <div class="space-y-6">
                <x-card title="Linimasa" icon="calendar">
                    <div class="space-y-5">
                        <x-form.field name="year" label="Tahun" required
                                      hint="Menentukan posisi pada linimasa." alpine>
                            <x-form.input id="year" name="year" type="number" min="1900" max="2200" alpine
                                          x-model.number="form.year" placeholder="2025" />
                        </x-form.field>

                        <x-form.toggle name="is_active" model="form.is_active"
                                       label="Tampilkan di website"
                                       hint="Matikan untuk menyembunyikan tanpa menghapus." />
                    </div>
                </x-card>

                <x-card title="Gambar">
                    <x-form.image-upload name="cover" model="form.cover" />
                </x-card>
            </div>
        </div>
    </form>
</x-app-layout>
