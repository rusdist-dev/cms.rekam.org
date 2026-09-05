@props([
    'variant' => 'info',
    'title' => null,
    'dismissible' => false,
    'icon' => null,
])

@php
    $variants = [
        'info' => ['wrap' => 'bg-primary-50 text-primary-800 ring-primary-600/20', 'icon' => 'text-primary-500', 'name' => 'information-circle'],
        'success' => ['wrap' => 'bg-success-50 text-success-800 ring-success-600/20', 'icon' => 'text-success-500', 'name' => 'check-circle'],
        'warning' => ['wrap' => 'bg-warning-50 text-warning-800 ring-warning-600/20', 'icon' => 'text-warning-500', 'name' => 'exclamation-triangle'],
        'danger' => ['wrap' => 'bg-danger-50 text-danger-800 ring-danger-600/20', 'icon' => 'text-danger-500', 'name' => 'exclamation-circle'],
    ];

    $v = $variants[$variant] ?? $variants['info'];
    $iconName = $icon ?? $v['name'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded p-4 text-sm ring-1 ring-inset '.$v['wrap']]) }}
     role="{{ in_array($variant, ['danger', 'warning']) ? 'alert' : 'status' }}"
     @if ($dismissible) x-data="{ shown: true }" x-show="shown" x-transition @endif>
    <x-icon :name="$iconName" class="mt-0.5 h-5 w-5 {{ $v['icon'] }}" />

    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>

        @isset($actions)
            <div class="mt-3 flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    @if ($dismissible)
        <button type="button" @click="shown = false"
                class="-m-1 shrink-0 rounded p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-current"
                aria-label="Tutup pemberitahuan">
            <x-icon name="x-mark" class="h-4 w-4" />
        </button>
    @endif
</div>
