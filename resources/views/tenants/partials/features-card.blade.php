@php
    $features = config('cms.features');
@endphp

<x-card title="Modul Aktif"
        subtitle="Mematikan modul menyembunyikan menunya, menonaktifkan route-nya, dan mencabutnya dari API publik."
        :padding="false">

    <div class="divide-y divide-gray-100">
        @foreach ($features as $key => $feature)
            <div class="flex items-start justify-between gap-4 px-4 py-4 sm:px-6">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800">{{ $feature['label'] }}</p>
                    <p class="mt-0.5 font-mono text-xs text-gray-400">{{ $key }}</p>

                    @if ($feature['core'])
                        <p class="mt-1 text-xs text-gray-500">Modul inti — tidak dapat dimatikan.</p>
                    @endif
                </div>

                @if ($feature['core'])
                    <x-badge variant="gray" size="sm">Selalu aktif</x-badge>
                @else
                    <x-form.toggle model="form.features['{{ $key }}']" class="shrink-0" />
                @endif
            </div>
        @endforeach
    </div>

    <x-slot:footer>
        {{-- Turning a module off does not drop its tables: the content stays and
             reappears if the flag is switched back on. --}}
        <p class="text-xs text-gray-500">
            Mematikan modul tidak menghapus datanya. Konten tetap tersimpan dan muncul
            kembali bila modul diaktifkan lagi.
        </p>
    </x-slot:footer>
</x-card>
