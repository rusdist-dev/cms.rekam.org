@props([
    'name',
    'model' => null,
    'placeholder' => 'Ketik lalu tekan Enter…',
    'id' => null,
])

@php
    $id ??= $name;
@endphp

<div x-data="tagInput({{ $model ? "($model ?? [])" : '[]' }})"
     @if ($model) x-modelable="tags" x-model="{{ $model }}" @endif
     {{ $attributes->merge(['class' => 'flex min-h-[2.5rem] w-full flex-wrap items-center gap-1.5 rounded border border-gray-300 bg-white px-2.5 py-1.5 shadow-sm transition focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500']) }}>

    <template x-for="tag in tags" :key="tag">
        <span class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
            <span x-text="tag"></span>
            <button type="button" @click="remove(tag)"
                    class="rounded p-0.5 transition hover:bg-gray-200"
                    :aria-label="'Hapus ' + tag">
                <x-icon name="x-mark" class="h-3 w-3" />
            </button>
        </span>
    </template>

    <input type="text"
           id="{{ $id }}"
           x-model="draft"
           @keydown.enter.prevent="add()"
           @keydown.comma.prevent="add()"
           @keydown.backspace="backspace()"
           @blur="add()"
           placeholder="{{ $placeholder }}"
           class="min-w-[8rem] flex-1 border-0 p-0 text-sm placeholder:text-gray-400 focus:ring-0">

    <template x-for="tag in tags" :key="'input-' + tag">
        <input type="hidden" name="{{ $name }}[]" :value="tag">
    </template>
</div>
