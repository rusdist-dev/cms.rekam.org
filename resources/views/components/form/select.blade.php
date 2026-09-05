@props([
    'name' => null,
    // ['slug' => 'Label'] or [['value' => ..., 'label' => ...], ...]
    'options' => [],
    'placeholder' => null,
    'selected' => null,
    'size' => 'md',
    'alpine' => false,
    'id' => null,
])

@php
    $id ??= $name;

    $sizes = ['sm' => 'py-1.5 text-sm', 'md' => 'py-2 text-sm', 'lg' => 'py-2.5 text-base'];

    $normalised = collect($options)->map(function ($label, $key) {
        return is_array($label)
            ? ['value' => $label['value'] ?? $key, 'label' => $label['label'] ?? '']
            : ['value' => $key, 'label' => $label];
    })->values();

    $base = implode(' ', [
        'block w-full rounded border-gray-300 pe-9 ps-3 text-sm text-gray-900 shadow-sm transition',
        'focus:border-primary-500 focus:ring-primary-500',
        'disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500',
        $sizes[$size] ?? $sizes['md'],
    ]);

    $hasServerError = $name && $errors->has($name);
    $errorClasses = 'border-danger-400 focus:border-danger-500 focus:ring-danger-500';

    if ($hasServerError) {
        $base .= ' '.$errorClasses;
    }

    $alpineAttrs = ($alpine && $name) ? [
        ':class' => "fieldError('{$name}') ? '{$errorClasses}' : ''",
        ':aria-invalid' => "fieldError('{$name}') ? 'true' : 'false'",
    ] : [];

    $current = $selected ?? ($name ? old($name) : null);
@endphp

<select @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        @if ($hasServerError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->merge(['class' => $base])->merge($alpineAttrs) }}>

    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($normalised as $option)
        <option value="{{ $option['value'] }}"
                @selected((string) $current === (string) $option['value'])>{{ $option['label'] }}</option>
    @endforeach

    {{ $slot }}
</select>
