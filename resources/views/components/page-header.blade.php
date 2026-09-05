@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
    'back' => null,
])

{{-- Every page opens with this (context.md §7.7): breadcrumb, title, actions right. --}}
<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    @if (! empty($breadcrumbs))
        <x-breadcrumb :items="$breadcrumbs" class="mb-3" />
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            @if ($back)
                <x-button :href="$back" variant="secondary" size="sm" class="mt-0.5 !px-2"
                          aria-label="Kembali">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                </x-button>
            @endif

            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold text-gray-900 sm:text-2xl">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        @isset($actions)
            <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    @isset($slot)
        @if (trim($slot) !== '')
            <div class="mt-4">{{ $slot }}</div>
        @endif
    @endisset
</div>
