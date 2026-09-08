{{-- A menu entry needs BOTH gates: the tenant must have the module
     (@feature, context.md §5.6) and the user must be allowed to see it
     (@can, context.md §7.5). Either one missing hides the entry. --}}

<x-sidebar.item route="dashboard" icon="squares-2x2" label="Dasbor" pattern="dashboard" />

<x-sidebar.group name="konten" label="Konten">
    @feature('news')
        @can('news.view')
            <x-sidebar.item route="news.index" icon="newspaper" label="Berita" />
        @endcan
    @endfeature

    @feature('events')
        @can('events.view')
            <x-sidebar.item route="events.index" icon="calendar-days" label="Events" />
        @endcan
    @endfeature

    @feature('team')
        @can('team.view')
            <x-sidebar.item route="team.index" icon="users" label="Tim" />
        @endcan
    @endfeature

    @feature('publications')
        @can('publications.view')
            <x-sidebar.item route="publications.index" icon="document-text" label="Publikasi" />
        @endcan
    @endfeature

    @feature('partners')
        @can('partners.view')
            <x-sidebar.item route="partners.index" icon="building-office-2" label="Partner" />
        @endcan
    @endfeature

    @feature('milestones')
        @can('milestones.view')
            <x-sidebar.item route="milestones.index" icon="flag" label="Milestone" />
        @endcan
    @endfeature

    @feature('units')
        @can('units.view')
            <x-sidebar.item route="units.index" icon="squares-plus" label="Unit" />
        @endcan
    @endfeature
</x-sidebar.group>

@feature('contacts')
    @can('contacts.view')
        <x-sidebar.group name="interaksi" label="Interaksi">
            <x-sidebar.item route="contacts.index" icon="inbox" label="Kotak Masuk" :badge="$unreadMessages ?: null" />
        </x-sidebar.group>
    @endcan
@endfeature

@canany(['settings.view', 'tenants.view', 'users.view', 'roles.view', 'activity.view'])
    <x-sidebar.group name="sistem" label="Sistem">
        @can('settings.view')
            <x-sidebar.item route="settings.index" icon="cog-6-tooth" label="Pengaturan" />
        @endcan

        @can('tenants.view')
            <x-sidebar.item route="tenants.index" icon="building-storefront" label="Company" />
        @endcan

        @can('users.view')
            <x-sidebar.item route="users.index" icon="user-group" label="Pengguna" />
        @endcan

        @can('roles.view')
            <x-sidebar.item route="roles.index" icon="shield-check" label="Peran & Izin" />
        @endcan

        @can('activity.view')
            <x-sidebar.item route="activity.index" icon="clock" label="Riwayat Aktivitas" />
        @endcan
    </x-sidebar.group>
@endcanany
