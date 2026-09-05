@props([
    'name' => null,
    'model' => null,
    'ratio' => 'aspect-video',
    'hint' => null,
    'id' => null,
])

@php
    $id ??= $name;
    $maxKb = config('cms.media.image.max_kb');
    $mimes = config('cms.media.image.mimes');
    $hint ??= strtoupper(implode(', ', $mimes)).' · maks '.round($maxKb / 1024, 1).' MB';
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
           accept="{{ collect($mimes)->map(fn ($m) => 'image/'.$m)->implode(',') }}"
           @change="onSelect($event)">

    {{-- Empty: dropzone --}}
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
         class="flex cursor-pointer flex-col items-center justify-center rounded-card border-2 border-dashed px-6 py-8 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $ratio }}">

        <x-icon name="photo" class="h-8 w-8 text-gray-400" />
        <p class="mt-3 text-sm font-medium text-gray-700">Seret gambar ke sini atau klik untuk memilih</p>
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    </div>

    {{-- Filled: preview --}}
    <div x-show="hasValue" x-cloak class="relative overflow-hidden rounded-card border border-gray-200 bg-gray-100 {{ $ratio }}">
        <img :src="previewUrl" alt="Pratinjau gambar" class="h-full w-full object-cover">

        <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-gray-900/80 to-transparent px-3 py-2">
            <span class="min-w-0 truncate text-xs text-white" x-text="label"></span>

            <div class="flex shrink-0 items-center gap-1">
                <button type="button" @click="pick()"
                        class="rounded bg-white/90 p-1.5 text-gray-700 transition hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                        aria-label="Ganti gambar">
                    <x-icon name="arrow-path" class="h-4 w-4" />
                </button>

                <button type="button" @click="remove()"
                        class="rounded bg-white/90 p-1.5 text-danger-600 transition hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                        aria-label="Hapus gambar">
                    <x-icon name="trash" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>

    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-1.5 text-sm text-danger-600"></p>
</div>
