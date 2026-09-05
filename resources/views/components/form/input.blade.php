@props([
    'name' => null,
    'type' => 'text',
    'icon' => null,
    'iconRight' => null,
    'size' => 'md',
    'alpine' => false,
    'id' => null,
])

@php
    $id ??= $name;

    $sizes = [
        'sm' => 'py-1.5 text-sm',
        'md' => 'py-2 text-sm',
        'lg' => 'py-2.5 text-base',
    ];

    $padStart = $icon ? 'ps-9' : 'ps-3';
    $padEnd = $iconRight ? 'pe-9' : 'pe-3';

    $base = implode(' ', [
        'block w-full rounded border-gray-300 text-gray-900 shadow-sm transition',
        'placeholder:text-gray-400',
        'focus:border-primary-500 focus:ring-primary-500',
        'disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500',
        $sizes[$size] ?? $sizes['md'],
        $padStart,
        $padEnd,
    ]);

    // Server-rendered forms know their errors at render time; fetch-driven forms
    // only learn them after a 422, so the ring is bound instead.
    $hasServerError = $name && $errors->has($name);
    $errorClasses = 'border-danger-400 text-danger-900 focus:border-danger-500 focus:ring-danger-500';

    if ($hasServerError) {
        $base .= ' '.$errorClasses;
    }

    $alpineAttrs = ($alpine && $name) ? [
        ':class' => "fieldError('{$name}') ? '{$errorClasses}' : ''",
        ':aria-invalid' => "fieldError('{$name}') ? 'true' : 'false'",
        ':aria-describedby' => "fieldError('{$name}') ? '{$id}-error' : null",
    ] : [];
@endphp

<div class="relative">
    @if ($icon)
        <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
            <x-icon :name="$icon" class="h-4 w-4" />
        </span>
    @endif

    <input type="{{ $type }}"
           @if ($name) name="{{ $name }}" @endif
           @if ($id) id="{{ $id }}" @endif
           @if ($hasServerError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
           {{ $attributes->merge(['class' => $base])->merge($alpineAttrs) }}>

    @if ($iconRight)
        <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-gray-400">
            <x-icon :name="$iconRight" class="h-4 w-4" />
        </span>
    @endif
</div>
