<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="roleForm(
              '{{ route('dash-api.roles.index') }}',
              '{{ route('dash-api.permissions.index') }}',
              {
                  recordId: {{ $recordId ? (int) $recordId : 'null' }},
                  redirectTo: '{{ route('roles.index') }}',
              }
          )"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :back="route('roles.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('roles.index')">Batal</x-button>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        <div class="space-y-6">
            <x-card title="Identitas Peran">
                <x-form.field name="name" label="Slug Peran" required
                              hint="Huruf kecil, angka, dan tanda hubung. Hindari mengubahnya setelah dipakai." alpine>
                    <x-form.input id="name" name="name" alpine x-model="form.name"
                                  class="font-mono" placeholder="editor-konten" />
                </x-form.field>
            </x-card>

            @include('roles.partials.permission-matrix')
        </div>
    </form>
</x-app-layout>
