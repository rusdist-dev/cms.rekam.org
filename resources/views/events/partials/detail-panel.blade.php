<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-card title="Konten">
            <x-form.lang-tabs completeness="{ id: !!form.title.id, en: !!form.title.en }">
                @foreach (config('cms.locales') as $locale)
                    <x-form.lang-panel :locale="$locale">
                        <x-form.field name="title.{{ $locale }}" label="Judul Acara" :required="$locale === 'id'" alpine>
                            <x-form.input :id="'title.'.$locale" name="title.{{ $locale }}" alpine
                                          x-model="form.title.{{ $locale }}" />
                        </x-form.field>

                        <x-form.field name="slug.{{ $locale }}" label="Slug"
                                      hint="Dibuat otomatis dari judul, boleh diubah." alpine>
                            <x-form.input :id="'slug.'.$locale" name="slug.{{ $locale }}" alpine
                                          x-model="form.slug.{{ $locale }}" />
                        </x-form.field>

                        <x-form.field name="location.{{ $locale }}" label="Lokasi" :required="$locale === 'id'" alpine>
                            <x-form.input :id="'location.'.$locale" name="location.{{ $locale }}" alpine
                                          icon="map-pin" x-model="form.location.{{ $locale }}"
                                          placeholder="Nama tempat atau kota" />
                        </x-form.field>

                        <x-form.field name="description.{{ $locale }}" label="Deskripsi" :required="$locale === 'id'" alpine>
                            <x-form.editor :id="'description.'.$locale" model="form.description.{{ $locale }}" />
                        </x-form.field>
                    </x-form.lang-panel>
                @endforeach
            </x-form.lang-tabs>
        </x-card>

        <x-card title="Waktu & Pendaftaran" icon="calendar">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-form.field name="start_at" label="Mulai" required alpine>
                    <x-form.date id="start_at" name="start_at" type="datetime-local" alpine
                                 x-model="form.start_at" />
                </x-form.field>

                <x-form.field name="end_at" label="Selesai"
                              hint="Kosongkan bila acara satu hari." alpine>
                    <x-form.date id="end_at" name="end_at" type="datetime-local" alpine
                                 x-model="form.end_at" />
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.toggle name="is_all_day" model="form.is_all_day"
                                   label="Acara sepanjang hari"
                                   hint="Jam mulai dan selesai tidak ditampilkan di website." />
                </div>

                <x-form.field name="registration_url" label="Tautan Pendaftaran"
                              hint="Opsional. CMS ini tidak menangani pendaftaran." alpine
                              class="sm:col-span-2">
                    <x-form.input id="registration_url" name="registration_url" type="url" alpine
                                  icon="link" x-model="form.registration_url"
                                  placeholder="https://…" />
                </x-form.field>
            </div>
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Penerbitan" icon="paper-airplane">
            <x-form.field name="status" label="Status" required alpine>
                <x-form.select id="status" name="status" alpine
                               :options="config('cms.statuses')" x-model="form.status" />
            </x-form.field>
        </x-card>

        <x-card title="Klasifikasi" icon="tag">
            <div x-data="apiResource('{{ route('dash-api.taxonomy.show', 'event_categories') }}', [])">
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
        </x-card>

        @include('events.partials.fee-card')

        <x-card title="Gambar Sampul">
            <x-form.image-upload name="cover" model="coverValue" />
        </x-card>
    </div>
</div>
