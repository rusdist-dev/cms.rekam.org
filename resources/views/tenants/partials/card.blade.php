<x-card>
    <div class="flex items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-card bg-primary-50 text-primary-600">
                <x-icon name="building-storefront" class="h-6 w-6" />
            </span>

            <div class="min-w-0">
                <p class="flex items-center gap-2 font-semibold text-gray-900">
                    <span x-text="tenant.name"></span>

                    <template x-if="tenant.id === {{ app(\App\Services\TenantManager::class)->currentId() ?? 0 }}">
                        <x-badge variant="primary" size="sm">Aktif</x-badge>
                    </template>

                    <template x-if="! tenant.is_active">
                        <x-badge variant="gray" size="sm">Nonaktif</x-badge>
                    </template>
                </p>

                <p class="truncate text-sm text-gray-500" x-text="tenant.domain ?? '—'"></p>
                <p class="mt-0.5 font-mono text-xs text-gray-400" x-text="tenant.slug + ' · ' + tenant.db_name"></p>
            </div>
        </div>

        @can('tenants.update')
            <a :href="editUrl.replace('__ID__', tenant.id)"
               class="inline-flex shrink-0 items-center gap-2 rounded bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                <x-icon name="cog-6-tooth" class="h-4 w-4" />
                Kelola
            </a>
        @endcan
    </div>

    <div class="mt-4 border-t border-gray-100 pt-4">
        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400">Modul Aktif</p>

        <div class="flex flex-wrap gap-1.5">
            <template x-for="(enabled, key) in tenant.features" :key="key">
                <template x-if="enabled">
                    <x-badge variant="primary" size="sm">
                        <span x-text="featureLabels[key] ?? key"></span>
                    </x-badge>
                </template>
            </template>
        </div>

        <p class="mt-3 text-xs text-gray-400">
            <template x-if="tenant.has_api_key">
                <span>API key dibuat <span x-text="tenant.api_key_generated_at ?? '—'"></span></span>
            </template>
            <template x-if="! tenant.has_api_key">
                <span class="text-warning-600">Belum punya API key</span>
            </template>
        </p>
    </div>
</x-card>
