@props([
    'variant' => 'gray',
    'size' => 'md',
    'icon' => null,
    'dot' => false,
])

@php
    // `success` (emerald) is reserved for "Terbit" — context.md §7.2.
    $variants = [
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-500/20',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20',
        'success' => 'bg-success-50 text-success-700 ring-success-600/20',
        'warning' => 'bg-warning-50 text-warning-800 ring-warning-600/20',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20',
    ];

    $dots = [
        'gray' => 'bg-gray-400',
        'primary' => 'bg-primary-500',
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
    ];

    $sizes = [
        'sm' => 'gap-1 px-1.5 py-0.5 text-[11px]',
        'md' => 'gap-1.5 px-2 py-0.5 text-xs',
    ];

    $classes = implode(' ', [
        'inline-flex items-center rounded-full font-medium ring-1 ring-inset whitespace-nowrap',
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['gray'],
    ]);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dots[$variant] ?? $dots['gray'] }}"></span>
    @endif

    @if ($icon)
        <x-icon :name="$icon" class="h-3.5 w-3.5" />
    @endif

    {{ $slot }}
</span>
