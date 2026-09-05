@props([
    'name' => null,
    'label' => null,
    'hint' => null,
    'value' => 1,
    'id' => null,
])

@php
    $id ??= $name;
@endphp

<label @if ($id) for="{{ $id }}" @endif class="flex cursor-pointer items-start gap-2.5">
    <input type="checkbox"
           @if ($name) name="{{ $name }}" @endif
           @if ($id) id="{{ $id }}" @endif
           value="{{ $value }}"
           {{ $attributes->merge([
               'class' => 'mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600 shadow-sm transition focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100',
           ]) }}>

    @if ($label || trim($slot) !== '')
        <span class="min-w-0 text-sm">
            <span class="block text-gray-700">{{ $label ?? $slot }}</span>
            @if ($hint)
                <span class="mt-0.5 block text-xs text-gray-500">{{ $hint }}</span>
            @endif
        </span>
    @endif
</label>
