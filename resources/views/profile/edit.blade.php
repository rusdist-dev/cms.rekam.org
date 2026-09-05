<x-app-layout title="Profil Saya" :breadcrumbs="[['label' => 'Profil Saya']]">
    <x-page-header title="Profil Saya" subtitle="Ubah data akun dan kata sandi Anda." />

    <div class="max-w-3xl space-y-6">
        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
</x-app-layout>
