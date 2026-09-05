<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="resourceForm('{{ route('dash-api.team.index') }}', @js(\App\Support\FormDefaults::teamMember()), {
              recordId: {{ $recordId ? (int) $recordId : 'null' }},
              redirectTo: '{{ route('team.index') }}',
          })"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :back="route('team.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('team.index')">Batal</x-button>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="Identitas">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-form.field name="name" label="Nama Lengkap" required alpine>
                            <x-form.input id="name" name="name" alpine x-model="form.name" />
                        </x-form.field>

                        <x-form.field name="email" label="Email" alpine>
                            <x-form.input id="email" name="email" type="email" alpine
                                          icon="at-symbol" x-model="form.email" />
                        </x-form.field>

                        <x-form.field name="socials.linkedin" label="LinkedIn" alpine>
                            <x-form.input id="socials.linkedin" type="url" alpine
                                          icon="link" x-model="form.socials.linkedin"
                                          placeholder="https://linkedin.com/in/…" />
                        </x-form.field>

                        <x-form.field name="socials.instagram" label="Instagram" alpine>
                            <x-form.input id="socials.instagram" type="url" alpine
                                          icon="link" x-model="form.socials.instagram" />
                        </x-form.field>
                    </div>
                </x-card>

                <x-card title="Jabatan & Profil">
                    <x-form.lang-tabs completeness="{ id: !!form.position.id, en: !!form.position.en }">
                        @foreach (config('cms.locales') as $locale)
                            <x-form.lang-panel :locale="$locale">
                                <x-form.field name="position.{{ $locale }}" label="Jabatan" :required="$locale === 'id'" alpine>
                                    <x-form.input :id="'position.'.$locale" name="position.{{ $locale }}" alpine
                                                  x-model="form.position.{{ $locale }}"
                                                  placeholder="Direktur Program" />
                                </x-form.field>

                                <x-form.field name="bio.{{ $locale }}" label="Profil Singkat" alpine>
                                    <x-form.textarea :id="'bio.'.$locale" name="bio.{{ $locale }}" alpine
                                                     rows="5" x-model="form.bio.{{ $locale }}" />
                                </x-form.field>
                            </x-form.lang-panel>
                        @endforeach
                    </x-form.lang-tabs>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Penempatan" icon="user-group">
                    <div class="space-y-5">
                        <div x-data="apiResource('{{ route('dash-api.taxonomy.show', 'team_levels') }}', [])">
                            <x-form.field name="group" label="Level" required
                                          hint="Daftar level diatur di Pengaturan › Taksonomi." alpine>
                                <select id="group" x-model="form.group" :disabled="loading"
                                        :class="fieldError('group') ? 'border-danger-400' : 'border-gray-300'"
                                        class="block w-full rounded py-2 pe-9 ps-3 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-50">
                                    <option value="">Pilih level…</option>
                                    <template x-for="opt in (data ?? [])" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label"></option>
                                    </template>
                                </select>
                            </x-form.field>

                            <p x-show="error" x-cloak class="mt-1.5 text-xs text-danger-600">
                                Gagal memuat level.
                                <button type="button" @click="load()" class="underline">Coba lagi</button>
                            </p>
                        </div>

                        <x-form.toggle name="is_active" model="form.is_active"
                                       label="Tampilkan di website"
                                       hint="Matikan untuk menyembunyikan tanpa menghapus." />
                    </div>
                </x-card>

                <x-card title="Foto">
                    <x-form.image-upload name="photo" model="form.photo" ratio="aspect-square" />
                </x-card>
            </div>
        </div>
    </form>
</x-app-layout>
