@props([
    'align' => 'right',
    'width' => 'w-56',
])

@php
    $alignments = [
        'left' => 'left-0 origin-top-left',
        'right' => 'right-0 origin-top-right',
        'center' => 'left-1/2 -translate-x-1/2 origin-top',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }}
     x-data="{ open: false }"
     @keydown.escape.window="open = false"
     @click.outside="open = false">

    <div @click="open = ! open" :aria-expanded="open ? 'true' : 'false'" aria-haspopup="true">
        {{ $trigger }}
    </div>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click="open = false"
         class="absolute z-modal mt-2 {{ $width }} {{ $alignments[$align] ?? $alignments['right'] }} rounded-card border border-gray-200 bg-white py-1 shadow-overlay focus:outline-none"
         role="menu"
         aria-orientation="vertical">
        {{ $slot }}
    </div>
</div>
