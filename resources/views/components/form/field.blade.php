@props([
    'name' => null,
    'label' => null,
    'hint' => null,
    'required' => false,
    'for' => null,

    // Fetch-driven forms map 422 errors themselves; `alpine` wires this field to
    // resourceForm()'s error bag without every caller repeating the expression.
    'alpine' => false,
    'errorBind' => null,
])

@php
    $for ??= $name;
    $errorId = $for ? $for.'-error' : null;
    $hintId = ($hint && $for) ? $for.'-hint' : null;

    $errorBind ??= ($alpine && $name) ? "fieldError('{$name}')" : null;
    $hasServerError = $name && $errors->has($name);
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif
               class="mb-1.5 block text-sm font-medium text-gray-700">
            {{ $label }}
            @if ($required)
                <span class="text-danger-600" aria-hidden="true">*</span>
                <span class="sr-only">(wajib diisi)</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p @if ($hintId) id="{{ $hintId }}" @endif class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif

    <x-form.error :name="$name" :bind="$errorBind" :id="$errorId" />
</div>
