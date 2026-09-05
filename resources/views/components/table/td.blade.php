@props([
    'align' => 'left',
    'muted' => false,
    'nowrap' => false,
])

@php
    $aligns = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'];

    $classes = implode(' ', [
        'px-4 py-3 align-middle',
        $muted ? 'text-gray-500' : 'text-gray-800',
        $nowrap ? 'whitespace-nowrap' : '',
        $aligns[$align] ?? $aligns['left'],
    ]);
@endphp

<td {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</td>
