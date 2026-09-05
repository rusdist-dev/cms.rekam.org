@props([
    'colspan' => 1,
    'variant' => 'default',
])

@php
    $tone = $variant === 'danger' ? 'bg-danger-50/40' : '';
@endphp

<tr>
    <td colspan="{{ $colspan }}" {{ $attributes->merge(['class' => 'px-4 py-0 '.$tone]) }}>
        {{ $slot }}
    </td>
</tr>
