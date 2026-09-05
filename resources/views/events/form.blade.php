<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="contentForm('{{ route('dash-api.events.index') }}', @js(\App\Support\FormDefaults::event()), {
              recordId: {{ $recordId ? (int) $recordId : 'null' }},
              redirectTo: '{{ route('events.index') }}',
          })"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :back="route('events.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('events.index')">Batal</x-button>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        @php
            // The rundown tab only exists for tenants that have the feature
            // (context.md §5.6) — plan.md §5.2.d.
            $tabs = ['detail' => 'Detail Acara'];

            if (app(\App\Services\TenantManager::class)->hasFeature('event_rundown')) {
                $tabs['rundown'] = 'Rundown';
            }

            $tabs['seo'] = 'SEO';
        @endphp

        <x-tabs :tabs="$tabs" remember="events-form">
            <x-tab-panel name="detail">
                @include('events.partials.detail-panel')
            </x-tab-panel>

            @feature('event_rundown')
                <x-tab-panel name="rundown">
                    @include('events.partials.rundown-panel')
                </x-tab-panel>
            @endfeature

            <x-tab-panel name="seo">
                @include('shared.seo-card')
            </x-tab-panel>
        </x-tabs>
    </form>
</x-app-layout>
