@props([
    'headers' => [],
])

{{-- Horizontal scroll on small screens rather than a squashed table
     (context.md §7.8). --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-card border border-gray-200 bg-white shadow-card']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            @if (! empty($headers))
                <thead class="bg-gray-50">
                    <tr>
                        @foreach ($headers as $header)
                            @if (is_array($header))
                                <x-table.th :sort="$header['sort'] ?? null" :align="$header['align'] ?? 'left'">
                                    {{ $header['label'] ?? '' }}
                                </x-table.th>
                            @else
                                <x-table.th>{{ $header }}</x-table.th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
            @else
                <thead class="bg-gray-50">{{ $head ?? '' }}</thead>
            @endif

            <tbody class="divide-y divide-gray-100">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">{{ $footer }}</div>
    @endisset
</div>
