<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900">Lupa Kata Sandi</h1>
        <p class="mt-1 text-sm text-gray-500">
            Masukkan email Anda, kami kirimkan tautan untuk membuat kata sandi baru.
        </p>
    </div>

    @if (session('status'))
        <x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-form.field name="email" label="Email" required>
            <x-form.input name="email" type="email" icon="at-symbol"
                          :value="old('email')" required autofocus autocomplete="username" />
        </x-form.field>

        <x-button type="submit" variant="primary" size="lg" block>Kirim Tautan Reset</x-button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}"
               class="rounded text-primary-600 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                Kembali ke halaman masuk
            </a>
        </p>
    </form>
</x-guest-layout>
