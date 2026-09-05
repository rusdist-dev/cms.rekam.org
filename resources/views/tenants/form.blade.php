<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="tenantForm(
              '{{ route('dash-api.tenants.index') }}',
              {{ (int) $recordId }},
              '{{ route('dash-api.tenants.api-key', $recordId) }}'
          )"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :back="route('tenants.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('tenants.index')">Batal</x-button>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @include('tenants.partials.identity-card')
                @include('tenants.partials.features-card')
            </div>

            <div class="space-y-6">
                @include('tenants.partials.database-card')
                @include('tenants.partials.api-key-card')
            </div>
        </div>
    </form>
</x-app-layout>
