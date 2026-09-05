@props([
    'searchPlaceholder' => 'Cari…',
    'search' => true,
])

<div {{ $attributes->merge(['class' => 'mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="flex flex-1 flex-wrap items-center gap-2">
        @if ($search)
            <div class="w-full sm:max-w-xs">
                <label for="table-search" class="sr-only">{{ $searchPlaceholder }}</label>
                <x-form.input id="table-search" size="sm" icon="magnifying-glass" type="search"
                              :placeholder="$searchPlaceholder"
                              x-model.debounce.400ms="filters.search"
                              @input.debounce.400ms="applyFilters()" />
            </div>
        @endif

        {{ $slot }}

        <x-button type="button" size="sm" variant="ghost" icon="x-mark"
                  x-show="hasActiveFilters" x-cloak
                  @click="resetFilters()">
            Reset filter
        </x-button>
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
