@props([
    'name' => 'Pengguna',
    'email' => null,
    'role' => null,
    'avatar' => null,
])

<x-dropdown align="right" width="w-60" {{ $attributes }}>
    <x-slot:trigger>
        <button type="button"
                class="flex items-center gap-2 rounded p-1 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                aria-label="Menu pengguna">
            <x-avatar :name="$name" :src="$avatar" size="sm" />
            <span class="hidden min-w-0 text-left sm:block">
                <span class="block max-w-[9rem] truncate text-sm font-medium text-gray-800">{{ $name }}</span>
                @if ($role)
                    <span class="block max-w-[9rem] truncate text-xs text-gray-500">{{ $role }}</span>
                @endif
            </span>
            <x-icon name="chevron-down" class="h-4 w-4 text-gray-400" />
        </button>
    </x-slot:trigger>

    <div class="border-b border-gray-200 px-3 py-2.5">
        <p class="truncate text-sm font-medium text-gray-900">{{ $name }}</p>
        @if ($email)
            <p class="truncate text-xs text-gray-500">{{ $email }}</p>
        @endif
    </div>

    <x-dropdown.item :href="Route::has('profile.edit') ? route('profile.edit') : '#'" icon="user-circle">
        Profil Saya
    </x-dropdown.item>

    @if (Route::has('settings.index'))
        <x-dropdown.item :href="route('settings.index')" icon="cog-6-tooth">Pengaturan</x-dropdown.item>
    @endif

    <x-dropdown.divider />

    @if (Route::has('logout'))
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-dropdown.item type="submit" icon="arrow-right-on-rectangle" variant="danger">
                Keluar
            </x-dropdown.item>
        </form>
    @endif
</x-dropdown>
