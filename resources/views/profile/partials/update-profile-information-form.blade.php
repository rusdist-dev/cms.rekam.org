<x-card title="Informasi Akun" subtitle="Perbarui nama dan alamat email Anda." icon="user-circle">
    {{-- A full-page POST, not fetch: this is an auth-adjacent form outside the
         module CRUD flow, so Laravel's session errors are the right channel. --}}
    <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('PATCH')

        <x-form.field name="name" label="Nama" required>
            <x-form.input name="name" :value="old('name', $user->name)" required autocomplete="name" />
        </x-form.field>

        <x-form.field name="email" label="Email" required>
            <x-form.input name="email" type="email" icon="at-symbol"
                          :value="old('email', $user->email)" required autocomplete="username" />
        </x-form.field>

        <div class="flex items-center gap-3">
            <x-button type="submit" icon="check">Simpan</x-button>

            @if (session('status') === 'profile-updated')
                <p class="text-sm text-success-600" x-data="{ shown: true }" x-show="shown"
                   x-init="setTimeout(() => shown = false, 3000)">
                    Perubahan tersimpan.
                </p>
            @endif
        </div>
    </form>
</x-card>
