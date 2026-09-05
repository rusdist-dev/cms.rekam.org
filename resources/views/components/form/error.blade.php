@props([
    'name' => null,
    // Alpine expression returning a message (or falsy). Used by fetch-driven
    // forms where 422 errors never reach Laravel's $errors bag.
    'bind' => null,
    'id' => null,
])

@php
    $serverError = $name ? $errors->first($name) : null;
@endphp

@if ($bind)
    <p @if ($id) id="{{ $id }}" @endif
       x-show="{{ $bind }}" x-cloak
       {{ $attributes->merge(['class' => 'mt-1.5 flex items-start gap-1 text-sm text-danger-600']) }}
       role="alert">
        <x-icon name="exclamation-circle" variant="solid" class="mt-0.5 h-4 w-4 shrink-0" />
        <span x-text="{{ $bind }}"></span>
    </p>
@elseif ($serverError)
    <p @if ($id) id="{{ $id }}" @endif
       {{ $attributes->merge(['class' => 'mt-1.5 flex items-start gap-1 text-sm text-danger-600']) }}
       role="alert">
        <x-icon name="exclamation-circle" variant="solid" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ $serverError }}</span>
    </p>
@endif
