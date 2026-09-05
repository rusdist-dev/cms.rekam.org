@props([
    'editUrl' => null,
    'viewUrl' => null,
    'onDelete' => null,
    'deleteLabel' => 'Hapus',
])

<div {{ $attributes->merge(['class' => 'flex items-center justify-end gap-0.5']) }}>
    @if ($viewUrl)
        <a :href="{{ $viewUrl }}"
           class="rounded p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
           aria-label="Lihat detail">
            <x-icon name="eye" class="h-4 w-4" />
        </a>
    @endif

    @if ($editUrl)
        <a :href="{{ $editUrl }}"
           class="rounded p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
           aria-label="Ubah">
            <x-icon name="pencil-square" class="h-4 w-4" />
        </a>
    @endif

    @if ($onDelete)
        {{-- Destructive actions go through <x-modal.confirm>, never the browser's
             confirm() (context.md §7.11). The caller opens the modal here. --}}
        <button type="button" @click="{{ $onDelete }}"
                class="rounded p-1.5 text-gray-400 transition hover:bg-danger-50 hover:text-danger-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500"
                aria-label="{{ $deleteLabel }}">
            <x-icon name="trash" class="h-4 w-4" />
        </button>
    @endif

    {{ $slot }}
</div>
