@props([
    // [['label' => 'Berita', 'url' => route(...)], ['label' => 'Tambah']]
    'items' => [],
    'home' => null,
])

@php
    $items = collect($items)->filter()->values();
    $homeUrl = $home ?? (\Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/'));
@endphp

<nav {{ $attributes->merge(['class' => 'flex']) }} aria-label="Remah roti">
    <ol role="list" class="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm">
        <li>
            <a href="{{ $homeUrl }}"
               class="flex items-center rounded p-0.5 text-gray-400 transition hover:text-gray-600"
               aria-label="Dasbor">
                <x-icon name="squares-2x2" class="h-4 w-4" />
            </a>
        </li>

        @foreach ($items as $item)
            @php
                $label = is_array($item) ? ($item['label'] ?? '') : $item;
                $url = is_array($item) ? ($item['url'] ?? null) : null;
                $isLast = $loop->last;
            @endphp

            <li class="flex items-center gap-1">
                <x-icon name="chevron-right" class="h-4 w-4 text-gray-300" />

                @if ($url && ! $isLast)
                    <a href="{{ $url }}" class="rounded px-0.5 text-gray-500 transition hover:text-gray-800">{{ $label }}</a>
                @else
                    <span class="px-0.5 font-medium text-gray-800" @if ($isLast) aria-current="page" @endif>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
