<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <x-page-header title="Pengaturan Situs"
                   subtitle="Berlaku untuk company yang sedang aktif saja." />

    <x-tabs :tabs="$tabs" remember="settings">
        <x-tab-panel name="identitas">@include('settings.partials.identity')</x-tab-panel>
        <x-tab-panel name="sosial">@include('settings.partials.socials')</x-tab-panel>
        <x-tab-panel name="seo">@include('settings.partials.seo')</x-tab-panel>
        <x-tab-panel name="peta">@include('settings.partials.map')</x-tab-panel>
        <x-tab-panel name="taksonomi">@include('settings.partials.taxonomy')</x-tab-panel>
        <x-tab-panel name="api">@include('settings.partials.api-key', ['currentTenant' => $currentTenant])</x-tab-panel>
    </x-tabs>
</x-app-layout>
