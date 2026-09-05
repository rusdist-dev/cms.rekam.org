<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900">Masuk</h1>
        <p class="mt-1 text-sm text-gray-500">Gunakan akun yang diberikan administrator.</p>
    </div>

    @if (session('status'))
        <x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any() && ! $errors->has('email') && ! $errors->has('password'))
        <x-alert variant="danger" class="mb-5">{{ $errors->first() }}</x-alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-form.field name="email" label="Email" required>
            <x-form.input name="email" type="email" icon="at-symbol"
                          :value="old('email')"
                          placeholder="nama@rekam.org"
                          required autofocus autocomplete="username" />
        </x-form.field>

        <x-form.field name="password" label="Kata Sandi" required>
            <x-form.input name="password" type="password" icon="lock-closed"
                          placeholder="••••••••"
                          required autocomplete="current-password" />
        </x-form.field>

        <div class="flex items-center justify-between gap-3">
            <x-form.checkbox name="remember" label="Ingat saya" />

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="rounded text-sm text-primary-600 transition hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <x-button type="submit" variant="primary" size="lg" block>Masuk</x-button>
    </form>
</x-guest-layout>
