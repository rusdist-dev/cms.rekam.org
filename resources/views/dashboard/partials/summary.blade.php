{{-- Counters come from the internal API like every other figure in the CMS
     (context.md §4.1) — the controller passes no numbers. --}}
<div x-data="apiResource('{{ route('dash-api.stats.show', 'summary') }}', {})">
    <div x-show="error" x-cloak class="mb-4">
        <x-alert variant="danger" title="Gagal memuat ringkasan">
            <span x-text="error"></span>
            <x-slot:actions>
                <x-button size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
            </x-slot:actions>
        </x-alert>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Berita Terbit" icon="newspaper" variant="primary"
                     value-bind="data.news_published ?? 0" loading="loading"
                     :href="route('news.index')" />

        <x-stat-card label="Draf Menunggu" icon="clock" variant="warning"
                     value-bind="data.news_draft ?? 0" loading="loading"
                     :href="route('news.index')" />

        @feature('events')
            <x-stat-card label="Event Mendatang" icon="calendar-days" variant="success"
                         value-bind="data.events_upcoming ?? 0" loading="loading"
                         :href="route('events.index')" />
        @endfeature

        @feature('contacts')
            <x-stat-card label="Pesan Belum Dibaca" icon="inbox" variant="danger"
                         value-bind="data.messages_unread ?? 0" loading="loading"
                         :href="route('contacts.index')" />
        @endfeature
    </div>
</div>
