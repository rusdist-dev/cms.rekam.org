<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    @include('layouts.partials.head')
</head>
<body class="h-full font-sans">
    {{-- One Alpine root owns the shell: the rail, the drawer and the content
         offset all read the same collapsed/open state. --}}
    <div x-data="sidebar(@js($openGroups))" class="min-h-full">

        <x-sidebar>
            @include('layouts.partials.sidebar-nav')
        </x-sidebar>

        <div class="flex min-h-full flex-col transition-[padding] duration-200"
             :class="collapsed ? 'lg:pl-sidebar-collapsed' : 'lg:pl-sidebar'">

            <x-topbar
                :breadcrumbs="$breadcrumbs"
                :tenants="$chromeTenants ?? []"
                :unread-messages="$unreadMessages"
                :user="$chromeUser ?? []" />

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @include('layouts.partials.flash')

                {{ $slot }}
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
