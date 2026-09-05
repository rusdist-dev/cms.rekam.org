@props([
    // Column key the API sorts by. Omit for a non-sortable column.
    'sort' => null,
    'align' => 'left',
    'width' => null,
])

@php
    $aligns = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'];

    $classes = implode(' ', [
        'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 whitespace-nowrap',
        $aligns[$align] ?? $aligns['left'],
    ]);
@endphp

<th scope="col" @if ($width) style="width: {{ $width }}" @endif
    @if ($sort) ::aria-sort="sort === '{{ $sort }}' ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'" @endif
    {{ $attributes->merge(['class' => $classes]) }}>

    @if ($sort)
        <button type="button" @click="sortBy('{{ $sort }}')"
                class="group inline-flex items-center gap-1 rounded transition hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            {{ $slot }}

            <span class="text-gray-300 transition group-hover:text-gray-500"
                  ::class="sort === '{{ $sort }}' ? 'text-primary-600' : ''">
                <x-icon name="chevron-up-down" class="h-3.5 w-3.5"
                        x-show="sort !== '{{ $sort }}'" />
                <x-icon name="chevron-up" class="h-3.5 w-3.5"
                        x-show="sort === '{{ $sort }}' && direction === 'asc'" x-cloak />
                <x-icon name="chevron-down" class="h-3.5 w-3.5"
                        x-show="sort === '{{ $sort }}' && direction === 'desc'" x-cloak />
            </span>
        </button>
    @else
        {{ $slot }}
    @endif
</th>
