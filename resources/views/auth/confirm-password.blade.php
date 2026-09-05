<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900">Konfirmasi Kata Sandi</h1>
        <p class="mt-1 text-sm text-gray-500">
            Ini area sensitif. Masukkan kata sandi Anda untuk melanjutkan.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <x-form.field name="password" label="Kata Sandi" required>
            <x-form.input name="password" type="password" icon="lock-closed"
                          required autofocus autocomplete="current-password" />
        </x-form.field>

        <x-button type="submit" variant="primary" size="lg" block>Konfirmasi</x-button>
    </form>
</x-guest-layout>
