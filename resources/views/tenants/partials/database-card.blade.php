<x-card title="Database" icon="cube-transparent">
    <dl class="space-y-4 text-sm">
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Slug</dt>
            <dd class="mt-0.5 font-mono text-gray-800" x-text="form.slug ?? '—'"></dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Nama Database</dt>
            <dd class="mt-0.5 font-mono text-gray-800" x-text="form.db_name ?? '—'"></dd>
        </div>
    </dl>

    {{-- Database names come from the tenants table only (context.md §5.2) —
         never entered or edited here. --}}
    <x-alert variant="info" class="mt-4">
        Slug dan nama database ditentukan saat company dibuat dan tidak dapat diubah
        dari antarmuka. Mengubahnya akan memutus CMS dari konten yang sudah ada.
    </x-alert>
</x-card>
