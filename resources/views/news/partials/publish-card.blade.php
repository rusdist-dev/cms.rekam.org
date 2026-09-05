<x-card title="Penerbitan" icon="paper-airplane">
    <div class="space-y-5">
        <x-form.field name="status" label="Status" required alpine>
            <x-form.select id="status" name="status" alpine
                           :options="config('cms.statuses')"
                           x-model="form.status" />
        </x-form.field>

        {{-- Only a scheduled post needs a date, so the field appears with it. --}}
        <div x-show="form.status === 'scheduled'" x-cloak>
            <x-form.field name="published_at" label="Jadwal Terbit" required
                          hint="Berita otomatis terbit pada waktu ini." alpine>
                <x-form.date id="published_at" name="published_at" type="datetime-local" alpine
                             x-model="form.published_at" />
            </x-form.field>
        </div>

        <div x-show="form.status === 'published'" x-cloak>
            <x-form.field name="published_at" label="Tanggal Terbit" alpine>
                <x-form.date id="published_at_manual" type="datetime-local" alpine
                             x-model="form.published_at" />
            </x-form.field>
        </div>

        <x-form.field name="author_name" label="Penulis" alpine>
            <x-form.input id="author_name" name="author_name" alpine icon="user-circle"
                          x-model="form.author_name" placeholder="Nama penulis" />
        </x-form.field>
    </div>
</x-card>
