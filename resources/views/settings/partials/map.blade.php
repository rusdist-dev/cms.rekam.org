<form x-data="resourceForm('{{ route('dash-api.taxonomy.show', 'site_map') }}', { embed: '' })"
      @submit.prevent="submit()">

    @include('shared.form-states')

    <x-card title="Sematan Peta" subtitle="Tempel kode embed dari Google Maps.">
        <x-form.field name="embed" label="Kode Sematan" alpine>
            <x-form.textarea id="embed" name="embed" alpine rows="6" x-model="form.embed"
                             placeholder="&lt;iframe src=&quot;https://www.google.com/maps/embed?…&quot;&gt;&lt;/iframe&gt;" />
        </x-form.field>

        <x-alert variant="warning" class="mt-5">
            Hanya tempel kode dari sumber tepercaya. Kode sematan dimuat apa adanya di website company.
        </x-alert>

        <x-slot:footer>
            <div class="flex justify-end">
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </div>
        </x-slot:footer>
    </x-card>
</form>
