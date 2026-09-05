<x-guest-layout>
    <div class="text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-warning-100 text-warning-600">
            <x-icon name="building-storefront" class="h-6 w-6" />
        </span>

        <h1 class="mt-4 text-lg font-semibold text-gray-900">Company Belum Tersedia</h1>
        <p class="mt-2 text-sm text-gray-600">{{ $message ?? 'Company aktif belum ditentukan.' }}</p>

        @auth
            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <x-button type="submit" variant="secondary" block icon="arrow-right-on-rectangle">
                    Keluar
                </x-button>
            </form>
        @endauth
    </div>
</x-guest-layout>
