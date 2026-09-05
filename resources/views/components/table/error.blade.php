@props(['colspan' => 1])

{{-- The `error` state: message plus a retry, never a silent blank table
     (context.md §2.3). --}}
<x-table.state :colspan="$colspan" variant="danger" x-show="error" x-cloak>
    <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-danger-100 text-danger-600">
            <x-icon name="exclamation-triangle" class="h-6 w-6" />
        </span>

        <div>
            <p class="font-semibold text-gray-900">Gagal memuat data</p>
            <p class="mt-1 text-sm text-gray-500" x-text="error"></p>
        </div>

        <x-button type="button" size="sm" variant="secondary" icon="arrow-path" @click="load()">
            Coba lagi
        </x-button>
    </div>
</x-table.state>
