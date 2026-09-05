@props([
    'label',
    'icon' => 'chart-bar',
    'variant' => 'primary',
    'href' => null,
    'hint' => null,

    // Static value, or an Alpine expression when the number comes from the API.
    'value' => null,
    'valueBind' => null,
    'trendBind' => null,
    'loading' => null,
])

@php
    $variants = [
        'primary' => 'bg-primary-50 text-primary-600',
        'warning' => 'bg-warning-50 text-warning-600',
        'danger' => 'bg-danger-50 text-danger-600',
        'success' => 'bg-success-50 text-success-600',
        'gray' => 'bg-gray-100 text-gray-500',
    ];

    $tag = $href ? 'a' : 'div';
    $classes = 'group relative flex items-start gap-4 rounded-card border border-gray-200 bg-white p-5 shadow-card transition'
        .($href ? ' hover:border-primary-300 hover:shadow-raised focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500' : '');
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $variants[$variant] ?? $variants['primary'] }}">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-gray-500">{{ $label }}</p>

        @if ($loading)
            <div x-show="{{ $loading }}" x-cloak class="mt-2 h-7 w-16 skeleton"></div>
        @endif

        <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900"
           @if ($loading) x-show="! ({{ $loading }})" @endif
           @if ($valueBind) x-text="{{ $valueBind }}" @endif>{{ $value }}</p>

        @if ($trendBind)
            <p class="mt-1 text-xs" x-show="! ({{ $loading ?: 'false' }})" x-cloak>
                <span x-text="{{ $trendBind }}"></span>
            </p>
        @elseif ($hint)
            <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
        @endif
    </div>

    @if ($href)
        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 transition group-hover:text-primary-500" />
    @endif
</{{ $tag }}>
