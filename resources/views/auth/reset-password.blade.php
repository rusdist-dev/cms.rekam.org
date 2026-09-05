<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900">Kata Sandi Baru</h1>
        <p class="mt-1 text-sm text-gray-500">Buat kata sandi baru untuk akun Anda.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.field name="email" label="Email" required>
            <x-form.input name="email" type="email" icon="at-symbol"
                          :value="old('email', $request->email)"
                          required autofocus autocomplete="username" />
        </x-form.field>

        <x-form.field name="password" label="Kata Sandi Baru" required
                      hint="Minimal 8 karakter.">
            <x-form.input name="password" type="password" icon="lock-closed"
                          required autocomplete="new-password" />
        </x-form.field>

        <x-form.field name="password_confirmation" label="Ulangi Kata Sandi" required>
            <x-form.input name="password_confirmation" type="password" icon="lock-closed"
                          required autocomplete="new-password" />
        </x-form.field>

        <x-button type="submit" variant="primary" size="lg" block>Simpan Kata Sandi</x-button>
    </form>
</x-guest-layout>
