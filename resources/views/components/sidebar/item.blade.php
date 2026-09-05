@props([
    'label',
    'icon' => 'chevron-right',
    'route' => null,
    'href' => null,
    // Route pattern deciding the active state; defaults to the module wildcard
    // so `news.create` still highlights the "Berita" entry.
    'pattern' => null,
    'badge' => null,
    'badgeVariant' => 'danger',
    'nested' => false,
])

@php
    $target = $href ?? (($route && \Illuminate\Support\Facades\Route::has($route)) ? route($route) : '#');

    $pattern ??= $route ? \Illuminate\Support\Str::beforeLast($route, '.').'.*' : null;
    $active = $pattern ? request()->routeIs($pattern) : false;

    $state = $active
        ? 'bg-primary-50 text-primary-700 font-medium'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900';
@endphp

<a href="{{ $target }}"
   @if ($active) aria-current="page" @endif
   :title="collapsed ? '{{ $label }}' : null"
   {{ $attributes->merge([
       'class' => 'group relative flex items-center gap-3 rounded px-3 py-2 text-sm transition '
           .($nested ? 'ps-9 ' : '')
           .$state,
   ]) }}>

    {{-- Active rail marker stays visible in icon-only mode. --}}
    @if ($active)
        <span class="absolute inset-y-1 left-0 w-0.5 rounded-full bg-primary-600" aria-hidden="true"></span>
    @endif

    <x-icon :name="$icon" class="h-5 w-5 {{ $active ? 'text-primary-600' : 'text-gray-400 group-hover:text-gray-600' }}" />

    <span class="min-w-0 flex-1 truncate" x-show="! collapsed" x-cloak>{{ $label }}</span>

    @if ($badge)
        <x-badge :variant="$badgeVariant" size="sm" x-show="! collapsed" x-cloak>{{ $badge }}</x-badge>

        {{-- Collapsed rail has no room for a count, so it degrades to a dot. --}}
        <span x-show="collapsed" x-cloak
              class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-danger-500 ring-2 ring-white"
              aria-hidden="true"></span>
    @endif

    <span class="sr-only" x-show="collapsed">{{ $label }}</span>
</a>
