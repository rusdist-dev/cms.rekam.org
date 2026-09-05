@props([
    'breadcrumbs' => [],
    'tenants' => [],
    'currentTenant' => null,
    'unreadMessages' => 0,
    'user' => [],
])

<header {{ $attributes->merge(['class' => 'sticky top-0 z-topbar flex h-topbar items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6']) }}>
    {{-- Drawer trigger, mobile only --}}
    <button type="button"
            @click="openDrawer()"
            class="-ms-1 rounded p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 lg:hidden"
            aria-label="Buka menu">
        <x-icon name="bars-3" class="h-5 w-5" />
    </button>

    <x-topbar.tenant-switcher :tenants="$tenants" :current="$currentTenant" />

    @if (! empty($breadcrumbs))
        <x-breadcrumb :items="$breadcrumbs" class="hidden min-w-0 md:flex" />
    @endif

    <div class="flex-1"></div>

    {{-- Unread contact messages (context.md §7.6) --}}
    <a href="{{ Route::has('contacts.index') ? route('contacts.index') : '#' }}"
       class="relative rounded p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
       aria-label="{{ $unreadMessages > 0 ? "Pesan belum dibaca: {$unreadMessages}" : 'Kotak masuk pesan' }}">
        <x-icon name="inbox" class="h-5 w-5" />

        @if ($unreadMessages > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-semibold text-white ring-2 ring-white">
                {{ $unreadMessages > 99 ? '99+' : $unreadMessages }}
            </span>
        @endif
    </a>

    <span class="h-6 w-px bg-gray-200" aria-hidden="true"></span>

    <x-topbar.user-menu
        :name="$user['name'] ?? 'Pengguna'"
        :email="$user['email'] ?? null"
        :role="$user['role'] ?? null"
        :avatar="$user['avatar'] ?? null" />
</header>
