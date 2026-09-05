@props([
    'endpoint',
    'type' => 'line',
    'height' => 'h-64',
    'emptyTitle' => 'Belum ada data untuk ditampilkan',
])

{{-- The only sanctioned way to draw a chart (context.md §7.10): Chart.js, data
     fetched with a loading state, nothing embedded in the HTML. --}}
<div x-data="chart('{{ $endpoint }}', '{{ $type }}')"
     x-on:destroy="destroy()"
     {{ $attributes->merge(['class' => 'relative '.$height]) }}>

    {{-- loading --}}
    <div x-show="loading" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-3">
        <x-spinner size="lg" class="text-primary-500" />
        <p class="text-sm text-gray-400">Memuat grafik…</p>
    </div>

    {{-- error --}}
    <div x-show="error" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-center">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-danger-100 text-danger-600">
            <x-icon name="exclamation-triangle" class="h-5 w-5" />
        </span>
        <p class="text-sm text-gray-500" x-text="error"></p>
        <x-button type="button" size="sm" variant="secondary" icon="arrow-path" @click="load()">Coba lagi</x-button>
    </div>

    {{-- empty --}}
    <div x-show="! loading && ! error && isEmpty" x-cloak class="absolute inset-0 flex items-center justify-center">
        <x-empty-state :title="$emptyTitle" icon="chart-bar" size="sm" />
    </div>

    {{-- ready --}}
    <div x-show="! loading && ! error && ! isEmpty" class="h-full w-full">
        <canvas x-ref="canvas" role="img" aria-label="Grafik data"></canvas>
    </div>
</div>
