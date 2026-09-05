@props([
    'name' => null,
    'model' => null,
    'hint' => null,
    'id' => null,
])

@php
    $id ??= $name;
    $maxKb = config('cms.media.document.max_kb');
    $mimes = config('cms.media.document.mimes');
    $hint ??= strtoupper(implode(', ', $mimes)).' · maks '.round($maxKb / 1024).' MB';
@endphp

<div x-data="mediaPicker({{ $model ? "($model ?? null)" : 'null' }}, { maxKb: {{ $maxKb }}, accept: @js($mimes) })"
     @if ($model) x-modelable="value" x-model="{{ $model }}" @endif
     @if ($name)
         {{-- The picked File goes to the parent form's `files` map, and a
              cleared field sets remove_* so the server knows the difference
              between "unchanged" and "deleted". --}}
         x-effect="
             if (typeof files !== 'undefined') files['{{ $name }}'] = file;
             if (typeof form !== 'undefined') form['remove_{{ $name }}'] = ! hasValue;
         "
     @endif
     {{ $attributes }}>

    <input type="file" x-ref="input" class="sr-only"
           @if ($name) name="{{ $name }}" @endif
           @if ($id) id="{{ $id }}" @endif
           accept="{{ collect($mimes)->map(fn ($m) => '.'.$m)->implode(',') }}"
           @change="onSelect($event)">

    <div x-show="! hasValue" x-cloak
         @click="pick()"
         @dragover.prevent="dragging = true"
         @dragleave.prevent="dragging = false"
         @drop.prevent="onDrop($event)"
         @keydown.enter="pick()"
         @keydown.space.prevent="pick()"
         role="button"
         tabindex="0"
         :class="dragging ? 'border-primary-400 bg-primary-50' : 'border-gray-300 bg-gray-50 hover:border-primary-300 hover:bg-gray-100'"
         class="flex cursor-pointer items-center gap-3 rounded-card border-2 border-dashed px-4 py-4 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">

        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-gray-400 ring-1 ring-gray-200">
            <x-icon name="arrow-up-tray" class="h-5 w-5" />
        </span>

        <span class="min-w-0">
            <span class="block text-sm font-medium text-gray-700">Pilih berkas atau seret ke sini</span>
            <span class="block text-xs text-gray-500">{{ $hint }}</span>
        </span>
    </div>

    <div x-show="hasValue" x-cloak
         class="flex items-center gap-3 rounded-card border border-gray-200 bg-white px-4 py-3">

        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-danger-50 text-danger-600">
            <x-icon name="document-text" class="h-5 w-5" />
        </span>

        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-gray-800" x-text="value?.name ?? value?.path"></span>
            <span class="block text-xs text-gray-500" x-text="label"></span>
        </span>

        <span class="flex shrink-0 items-center gap-1">
            <a x-show="value?.url && ! value.url.startsWith('blob:')" :href="value?.url" target="_blank" rel="noopener"
               class="rounded p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
               aria-label="Buka berkas">
                <x-icon name="arrow-down-tray" class="h-4 w-4" />
            </a>

            <button type="button" @click="pick()"
                    class="rounded p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    aria-label="Ganti berkas">
                <x-icon name="arrow-path" class="h-4 w-4" />
            </button>

            <button type="button" @click="remove()"
                    class="rounded p-1.5 text-gray-400 transition hover:bg-danger-50 hover:text-danger-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500"
                    aria-label="Hapus berkas">
                <x-icon name="trash" class="h-4 w-4" />
            </button>
        </span>
    </div>

    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-1.5 text-sm text-danger-600"></p>
</div>
