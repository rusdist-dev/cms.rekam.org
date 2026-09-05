@props([
    'show' => 'false',
    'title' => null,
    'description' => null,
    'size' => 'md',
    'side' => 'right',
    // Alpine statement run to dismiss the panel. See the identical prop on
    // modal.blade.php — required whenever `show` is a computed expression
    // rather than a bare variable.
    'onClose' => null,
])

@php
    $sizes = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-xl', 'xl' => 'max-w-3xl'];
    $isRight = $side === 'right';

    $id = 'drawer-'.\Illuminate\Support\Str::random(8);
    $close = $onClose ?: "{$show} = false";
@endphp

<template x-teleport="body">
    <div x-show="{{ $show }}" x-cloak
         @keydown.escape.window="{{ $close }}"
         class="fixed inset-0 z-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="{{ $id }}-title">

        <div x-show="{{ $show }}"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="{{ $close }}"
             class="absolute inset-0 bg-gray-900/50"
             aria-hidden="true"></div>

        <div class="absolute inset-y-0 {{ $isRight ? 'right-0' : 'left-0' }} flex w-full {{ $sizes[$size] ?? $sizes['md'] }}">
            <div x-show="{{ $show }}"
                 x-transition:enter="transform transition ease-out duration-250"
                 x-transition:enter-start="{{ $isRight ? 'translate-x-full' : '-translate-x-full' }}"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in duration-200"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="{{ $isRight ? 'translate-x-full' : '-translate-x-full' }}"
                 {{ $attributes->merge(['class' => 'flex h-full w-full flex-col bg-white shadow-overlay']) }}>

                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 px-5 py-4">
                    <div class="min-w-0">
                        @if ($title)
                            <h2 id="{{ $id }}-title" class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                        @endif
                        @if ($description)
                            <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
                        @endif
                    </div>

                    <button type="button" @click="{{ $close }}"
                            class="-m-1 shrink-0 rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            aria-label="Tutup panel">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="scrollbar-thin flex-1 overflow-y-auto px-5 py-4">{{ $slot }}</div>

                @isset($footer)
                    <div class="flex shrink-0 flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 sm:flex-row sm:justify-end">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </div>
</template>
