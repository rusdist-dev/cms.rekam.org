@props([
    'href' => null,
    'icon' => null,
    'variant' => 'default',
    'active' => false,
])

@php
    $variants = [
        'default' => 'text-gray-700 hover:bg-gray-100 hover:text-gray-900',
        'danger' => 'text-danger-600 hover:bg-danger-50',
    ];

    $classes = implode(' ', [
        'flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition focus:outline-none focus-visible:bg-gray-100',
        $active ? 'bg-primary-50 font-medium text-primary-700' : ($variants[$variant] ?? $variants['default']),
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4 shrink-0 opacity-70" />
        @endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
        @if ($active)
            <x-icon name="check" class="h-4 w-4 shrink-0 text-primary-600" />
        @endif
    </a>
@else
    {{-- `type` goes through merge so a caller can submit a form from the menu
         (logout) without emitting a duplicate, ignored attribute. --}}
    <button role="menuitem" {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4 shrink-0 opacity-70" />
        @endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
        @if ($active)
            <x-icon name="check" class="h-4 w-4 shrink-0 text-primary-600" />
        @endif
    </button>
@endif
