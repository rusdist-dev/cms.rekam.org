@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'padding' => true,
])

@php
    $body = $padding ? 'p-4 sm:p-6' : '';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-card border border-gray-200 bg-white shadow-card']) }}>
    @if ($title || $subtitle || isset($actions))
        <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-4 py-4 sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                @if ($icon)
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                        <x-icon :name="$icon" class="h-5 w-5" />
                    </span>
                @endif

                <div class="min-w-0">
                    @if ($title)
                        <h2 class="truncate text-base font-semibold text-gray-900">{{ $title }}</h2>
                    @endif
                    @if ($subtitle)
                        <p class="mt-0.5 text-sm text-gray-500">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>

            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $body }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="rounded-b-card border-t border-gray-200 bg-gray-50 px-4 py-3 sm:px-6">
            {{ $footer }}
        </div>
    @endisset
</div>
