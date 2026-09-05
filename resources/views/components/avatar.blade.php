@props([
    'name' => '',
    'src' => null,
    'size' => 'md',
    'square' => false,

    // Alpine expressions, for rows rendered client-side by x-for. Using these
    // keeps one avatar component instead of a hand-copied variant per table.
    'nameBind' => null,
    'srcBind' => null,
])

@php
    $sizes = [
        'xs' => 'h-6 w-6 text-[10px]',
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-10 w-10 text-sm',
        'lg' => 'h-14 w-14 text-base',
        'xl' => 'h-20 w-20 text-xl',
    ];

    // Initials from the first and last word — "Budi Santoso" reads as BS.
    // resources/js/initials.js mirrors this for the Alpine branch.
    $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = match (count($words)) {
        0 => '?',
        1 => mb_strtoupper(mb_substr($words[0], 0, 2)),
        default => mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1)),
    };

    $shape = $square ? 'rounded' : 'rounded-full';
    $base = 'shrink-0 overflow-hidden '.$shape.' '.($sizes[$size] ?? $sizes['md']);

    $imgClasses = $base.' object-cover bg-gray-100 ring-1 ring-gray-900/5';
    $fallbackClasses = $base.' inline-flex items-center justify-center bg-primary-100 font-semibold uppercase text-primary-700 ring-1 ring-primary-600/10';
@endphp

@if ($nameBind || $srcBind)
    @php $srcExpr = $srcBind ?: 'null'; @endphp

    <img x-show="{{ $srcExpr }}" :src="{{ $srcExpr }}" :alt="{{ $nameBind ?: "''" }}"
         {{ $attributes->merge(['class' => $imgClasses]) }}>

    <span x-show="! ({{ $srcExpr }})"
          {{ $attributes->merge(['class' => $fallbackClasses]) }}
          role="img"
          :aria-label="{{ $nameBind ?: "'Tanpa nama'" }}"
          x-text="$initials({{ $nameBind ?: "''" }})"></span>
@elseif ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->merge(['class' => $imgClasses]) }}>
@else
    <span {{ $attributes->merge(['class' => $fallbackClasses]) }}
          role="img" aria-label="{{ $name ?: 'Tanpa nama' }}">
        {{ $initials }}
    </span>
@endif
