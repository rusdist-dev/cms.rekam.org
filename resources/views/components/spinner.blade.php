@props([
    'size' => 'md',
])

@php
    $sizes = [
        'xs' => 'w-3 h-3 border',
        'sm' => 'w-4 h-4 border-2',
        'md' => 'w-5 h-5 border-2',
        'lg' => 'w-8 h-8 border-2',
        'xl' => 'w-12 h-12 border-[3px]',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-block shrink-0 animate-spin rounded-full border-current border-r-transparent align-[-0.125em] '.($sizes[$size] ?? $sizes['md'])]) }}
      role="status">
    <span class="sr-only">Memuat…</span>
</span>
