@props([
    // Alpine expression controlling visibility, e.g. "showEdit".
    'show' => 'false',
    'title' => null,
    'description' => null,
    'size' => 'md',
    'closeable' => true,
    // Alpine statement run to dismiss the modal (Escape, backdrop click, the
    // X button). Defaults to "show = false" (using whatever `show` was passed
    // as), which is only valid when `show` is a bare variable name. A caller
    // whose `show` is a computed
    // expression — e.g. "confirming !== null", the delete-confirmation
    // pattern, where `confirming` holds an id or null rather than a plain
    // boolean — MUST pass `onClose` explicitly: assigning to a `!==`
    // comparison is "Invalid left-hand side in assignment", not a dismiss.
    'onClose' => null,
])

@php
    $sizes = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-lg',
        'lg' => 'sm:max-w-2xl',
        'xl' => 'sm:max-w-4xl',
    ];

    $id = 'modal-'.\Illuminate\Support\Str::random(8);
    $close = $onClose ?: "{$show} = false";
@endphp

<template x-teleport="body">
    <div x-show="{{ $show }}" x-cloak
         @if ($closeable) @keydown.escape.window="{{ $close }}" @endif
         class="fixed inset-0 z-modal overflow-y-auto"
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
             @if ($closeable) @click="{{ $close }}" @endif
             class="fixed inset-0 bg-gray-900/50"
             aria-hidden="true"></div>

        <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
            <div x-show="{{ $show }}"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                 {{ $attributes->merge(['class' => 'relative w-full '.($sizes[$size] ?? $sizes['md']).' rounded-card bg-white shadow-overlay']) }}>

                @if ($title || $closeable)
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4">
                        <div class="min-w-0">
                            @if ($title)
                                <h2 id="{{ $id }}-title" class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                            @endif
                            @if ($description)
                                <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
                            @endif
                        </div>

                        @if ($closeable)
                            <button type="button" @click="{{ $close }}"
                                    class="-m-1 shrink-0 rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                    aria-label="Tutup dialog">
                                <x-icon name="x-mark" class="h-5 w-5" />
                            </button>
                        @endif
                    </div>
                @endif

                <div class="px-5 py-4">{{ $slot }}</div>

                @isset($footer)
                    <div class="flex flex-col-reverse gap-2 rounded-b-card border-t border-gray-200 bg-gray-50 px-5 py-4 sm:flex-row sm:justify-end">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </div>
</template>
