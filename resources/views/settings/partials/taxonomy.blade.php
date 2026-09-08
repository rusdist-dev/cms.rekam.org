{{-- The taxonomy editor is what makes "add a program" an editorial task rather
     than a deploy (context.md §5.12). Each list is one site_settings row. --}}
<div class="space-y-6">
    <x-alert variant="info">
        Daftar di bawah ini berlaku untuk company yang sedang aktif. Menambah atau
        mengubah pilihan langsung berlaku di formulir konten tanpa perlu rilis baru.
    </x-alert>

    {{-- News categories are a real relational table with their own CRUD
         (Fase 3, NewsCategoryApiController) — not a site_settings option list
         like the others below, so they get a dedicated widget rather than
         the generic slug/label repeater. --}}
    @feature('news')
        @include('settings.partials.news-category-list')
    @endfeature

    @php
        $lists = [
            ['group' => 'team_levels', 'title' => 'Level Tim', 'icon' => 'user-group', 'feature' => 'team',
             'hint' => 'Urutan di sini menentukan urutan kelompok pada halaman tim.'],
            ['group' => 'news_programs', 'title' => 'Program Berita', 'icon' => 'swatch', 'feature' => 'news_programs',
             'hint' => 'Dipakai pada kolom “Program Terkait” di formulir berita.'],
            ['group' => 'event_categories', 'title' => 'Kategori Event', 'icon' => 'calendar-days', 'feature' => 'events',
             'hint' => null],
            ['group' => 'publication_categories', 'title' => 'Kategori Publikasi', 'icon' => 'document-text', 'feature' => 'publications',
             'hint' => null],
        ];
    @endphp

    @foreach ($lists as $list)
        @feature($list['feature'])
            @include('settings.partials.taxonomy-list', [
                'group' => $list['group'],
                'listTitle' => $list['title'],
                'listIcon' => $list['icon'],
                'listHint' => $list['hint'],
            ])
        @endfeature
    @endforeach
</div>
