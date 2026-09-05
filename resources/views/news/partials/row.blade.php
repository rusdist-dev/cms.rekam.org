<x-table.td>
    <div class="flex items-start gap-3">
        <input type="checkbox" :checked="selected.includes(item.id)" @change="toggle(item.id)"
               class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
               :aria-label="'Pilih ' + item.title.id">

        <div class="min-w-0">
            <a :href="editUrl.replace('__ID__', item.id)"
               class="block max-w-md truncate font-medium text-gray-900 transition hover:text-primary-600"
               x-text="item.title.id"></a>

            <p class="mt-0.5 flex items-center gap-2 text-xs text-gray-400">
                <span x-text="item.author_name"></span>

                {{-- Translation completeness (plan.md §7 risk: empty EN compro). --}}
                <template x-if="! item.translation_complete.en">
                    <span class="inline-flex items-center gap-1 text-warning-600" title="Terjemahan Inggris belum lengkap">
                        <x-icon name="language" class="h-3.5 w-3.5" />
                        EN kosong
                    </span>
                </template>
            </p>
        </div>
    </div>
</x-table.td>

<x-table.td muted nowrap>
    <span x-text="item.category?.name?.id ?? '—'"></span>
</x-table.td>

<x-table.td>
    <div class="flex flex-wrap gap-1">
        <template x-for="program in (item.related_programs ?? [])" :key="program">
            <x-badge variant="gray" size="sm"><span x-text="program"></span></x-badge>
        </template>
        <template x-if="(item.related_programs ?? []).length === 0">
            <span class="text-xs text-gray-400">—</span>
        </template>
    </div>
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

<x-table.td muted nowrap>
    <span x-text="item.published_at ?? '—'"></span>
</x-table.td>

<x-table.td align="right">
    <x-table.row-actions
        edit-url="editUrl.replace('__ID__', item.id)"
        on-delete="confirming = item.id" />
</x-table.td>
