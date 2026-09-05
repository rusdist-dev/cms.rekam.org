@props([
    'title' => 'Belum ada data',
    'description' => null,
    'icon' => 'inbox',
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => ['wrap' => 'py-8', 'badge' => 'h-10 w-10', 'icon' => 'h-5 w-5', 'title' => 'text-sm'],
        'md' => ['wrap' => 'py-12', 'badge' => 'h-14 w-14', 'icon' => 'h-7 w-7', 'title' => 'text-base'],
        'lg' => ['wrap' => 'py-20', 'badge' => 'h-16 w-16', 'icon' => 'h-8 w-8', 'title' => 'text-lg'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 text-center '.$s['wrap']]) }}>
    <span class="flex {{ $s['badge'] }} items-center justify-center rounded-full bg-gray-100 text-gray-400">
        <x-icon :name="$icon" :class="$s['icon']" />
    </span>

    <p class="mt-4 font-semibold text-gray-900 {{ $s['title'] }}">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
