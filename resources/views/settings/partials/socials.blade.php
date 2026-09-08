<form x-data="settingsForm('{{ route('dash-api.settings.show', 'socials') }}', {
          instagram: '', linkedin: '', youtube: '', facebook: '', x: '', tiktok: '',
      })"
      @submit.prevent="submit()">

    @include('shared.form-states')

    <x-card title="Sosial Media" subtitle="Kosongkan yang tidak dipakai — ikon tidak akan tampil di website.">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            @foreach (['instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'tiktok' => 'TikTok'] as $key => $label)
                <x-form.field name="{{ $key }}" label="{{ $label }}" alpine>
                    <x-form.input id="{{ $key }}" name="{{ $key }}" type="url" alpine
                                  icon="link" x-model="form.{{ $key }}"
                                  placeholder="https://…" />
                </x-form.field>
            @endforeach
        </div>

        <x-slot:footer>
            <div class="flex justify-end">
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </div>
        </x-slot:footer>
    </x-card>
</form>
