@props([
    'href' => null,
])

@php
    $href ??= \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/');
@endphp

<div {{ $attributes->merge(['class' => 'flex h-topbar shrink-0 items-center gap-3 border-b border-gray-200 px-4']) }}>
    <a href="{{ $href }}"
       class="flex min-w-0 items-center gap-3 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-primary-600 text-sm font-bold text-white">
            CMS
        </span>

        <span class="min-w-0" x-show="! collapsed" x-cloak>
            <span class="block truncate text-sm font-semibold text-gray-900">{{ config('app.name') }}</span>
            <span class="block truncate text-xs text-gray-500">Back-office</span>
        </span>
    </a>
</div>
