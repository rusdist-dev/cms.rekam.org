<x-table.td>
    <a :href="editUrl.replace('__ID__', item.id)"
       class="block max-w-xs truncate font-medium text-gray-900 transition hover:text-primary-600"
       x-text="item.title.id"></a>

    <p class="mt-0.5 flex items-center gap-2 text-xs text-gray-400">
        <span x-text="item.category"></span>

        @feature('event_rundown')
            <template x-if="item.rundowns_count > 0">
                <span class="inline-flex items-center gap-1">
                    <x-icon name="queue-list" class="h-3.5 w-3.5" />
                    <span x-text="item.rundowns_count + ' sesi'"></span>
                </span>
            </template>
        @endfeature
    </p>
</x-table.td>

<x-table.td muted nowrap>
    <span x-text="item.start_at"></span>
</x-table.td>

<x-table.td muted>
    <span x-text="item.location?.id ?? '—'"></span>
</x-table.td>

<x-table.td nowrap>
    {{-- fee 0 or null means free — the compro shows the wording, not the zero. --}}
    <template x-if="! item.fee">
        <x-badge variant="success" size="sm">Gratis</x-badge>
    </template>
    <template x-if="item.fee">
        <span class="text-sm" x-text="'Rp' + item.fee.toLocaleString('id-ID')"></span>
    </template>
</x-table.td>

<x-table.td muted nowrap align="center">
    <span x-text="item.quota ?? '—'"></span>
</x-table.td>

<x-table.td nowrap>
    <template x-if="item.status === 'published'">
        <x-badge variant="success" dot>Terbit</x-badge>
    </template>
    <template x-if="item.status === 'draft'">
        <x-badge variant="gray" dot>Draf</x-badge>
    </template>
    <template x-if="item.status === 'scheduled'">
        <x-badge variant="warning" icon="clock">Terjadwal</x-badge>
    </template>
</x-table.td>

<x-table.td align="right">
    <x-table.row-actions
        edit-url="editUrl.replace('__ID__', item.id)"
        on-delete="confirming = item.id" />
</x-table.td>
