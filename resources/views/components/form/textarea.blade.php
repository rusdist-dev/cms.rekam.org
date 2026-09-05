@props([
    'name' => null,
    'rows' => 4,
    'alpine' => false,
    'id' => null,
])

@php
    $id ??= $name;

    $base = implode(' ', [
        'block w-full rounded border-gray-300 py-2 text-sm text-gray-900 shadow-sm transition',
        'placeholder:text-gray-400',
        'focus:border-primary-500 focus:ring-primary-500',
        'disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500',
    ]);

    $hasServerError = $name && $errors->has($name);
    $errorClasses = 'border-danger-400 text-danger-900 focus:border-danger-500 focus:ring-danger-500';

    if ($hasServerError) {
        $base .= ' '.$errorClasses;
    }

    $alpineAttrs = ($alpine && $name) ? [
        ':class' => "fieldError('{$name}') ? '{$errorClasses}' : ''",
        ':aria-invalid' => "fieldError('{$name}') ? 'true' : 'false'",
    ] : [];
@endphp

<textarea @if ($name) name="{{ $name }}" @endif
          @if ($id) id="{{ $id }}" @endif
          rows="{{ $rows }}"
          @if ($hasServerError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
          {{ $attributes->merge(['class' => $base])->merge($alpineAttrs) }}>{{ $slot }}</textarea>
