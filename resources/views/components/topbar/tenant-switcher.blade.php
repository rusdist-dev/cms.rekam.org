@props([
    'tenants' => [],
    'current' => null,
])

@php
    $tenants = collect($tenants);
    $current ??= $tenants->firstWhere('is_current', true) ?? $tenants->first();
    $onlyOne = $tenants->count() <= 1;
@endphp

@if ($current)
    @if ($onlyOne)
        {{-- A single tenant needs no switcher, but the active company must stay
             visible so nobody edits the wrong site by accident. --}}
        <span {{ $attributes->merge(['class' => 'flex items-center gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm']) }}>
            <x-icon name="building-storefront" class="h-4 w-4 text-gray-400" />
            <span class="font-medium text-gray-800">{{ $current['name'] }}</span>
        </span>
    @else
        <x-dropdown align="left" width="w-64" {{ $attributes }}>
            <x-slot:trigger>
                <button type="button"
                        class="flex max-w-[13rem] items-center gap-2 rounded border border-gray-200 bg-white px-3 py-1.5 text-sm transition hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        aria-label="Ganti company aktif">
                    <x-icon name="building-storefront" class="h-4 w-4 text-primary-600" />
                    <span class="min-w-0 flex-1 truncate text-left font-medium text-gray-800">{{ $current['name'] }}</span>
                    <x-icon name="chevron-up-down" class="h-4 w-4 text-gray-400" />
                </button>
            </x-slot:trigger>

            <x-dropdown.header>Pilih Company</x-dropdown.header>

            @foreach ($tenants as $tenant)
                {{-- Switching rebinds the tenant database connection, so it is a
                     state change: a form, never a GET link a prefetcher could fire. --}}
                <form method="POST" action="{{ $tenant['switch_url'] ?? '#' }}">
                    @csrf
                    @method('PUT')

                    <x-dropdown.item
                        type="submit"
                        icon="building-storefront"
                        :active="(bool) ($tenant['is_current'] ?? false)"
                        :disabled="(bool) ($tenant['is_current'] ?? false)">
                        <span class="block truncate">{{ $tenant['name'] }}</span>
                        <span class="block truncate text-xs text-gray-400">{{ $tenant['domain'] ?? '' }}</span>
                    </x-dropdown.item>
                </form>
            @endforeach
        </x-dropdown>
    @endif
@endif
