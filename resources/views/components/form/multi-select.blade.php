@props([
    'name',
    // [['value' => 'forest', 'label' => 'Forest'], ...] — from site_settings.
    'options' => [],
    // Alpine expression supplying the options instead, for taxonomies fetched
    // from the API after the page renders.
    'optionsBind' => null,
    'model' => null,
    'placeholder' => 'Pilih satu atau lebih…',
    'emptyText' => 'Tidak ada pilihan yang cocok.',
    'searchable' => true,
    'alpine' => false,
    'id' => null,
])

@php
    $id ??= $name;

    $normalised = collect($options)->map(fn ($o, $k) => is_array($o)
        ? ['value' => $o['value'] ?? $k, 'label' => $o['label'] ?? '']
        : ['value' => $k, 'label' => $o]
    )->values()->all();

    // When the parent form drives the value (fetch-based forms), bind to its
    // property; otherwise the component owns a local array.
    $initial = $model ? "($model ?? [])" : '[]';
@endphp

<div x-data="multiSelect(@js($normalised), {{ $initial }}, { searchable: {{ $searchable ? 'true' : 'false' }} })"
     @if ($optionsBind) x-effect="options = ({{ $optionsBind }}) ?? []" @endif
     @if ($model) x-modelable="selected" x-model="{{ $model }}" @endif
     @keydown.escape="close()"
     @click.outside="close()"
     {{ $attributes->merge(['class' => 'relative']) }}>

    <button type="button"
            id="{{ $id }}"
            @click="open = ! open"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="listbox"
            @if ($alpine) :class="fieldError('{{ $name }}') ? 'border-danger-400' : 'border-gray-300'" @endif
            class="flex min-h-[2.5rem] w-full flex-wrap items-center gap-1.5 rounded border border-gray-300 bg-white px-2.5 py-1.5 text-start text-sm shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">

        <template x-if="isEmpty">
            <span class="text-gray-400">{{ $placeholder }}</span>
        </template>

        <template x-for="option in selectedOptions" :key="option.value">
            <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20">
                <span x-text="option.label"></span>
                <span @click.stop="remove(option.value)"
                      class="cursor-pointer rounded-full p-0.5 transition hover:bg-primary-100"
                      role="button"
                      tabindex="0"
                      @keydown.enter.stop="remove(option.value)"
                      :aria-label="'Hapus ' + option.label">
                    <x-icon name="x-mark" class="h-3 w-3" />
                </span>
            </span>
        </template>

        <span class="ms-auto flex items-center gap-1 ps-1">
            <x-icon name="chevron-up-down" class="h-4 w-4 shrink-0 text-gray-400" />
        </span>
    </button>

    {{-- Slugs are posted as a real array so a non-JS submit still works. --}}
    <template x-for="value in selected" :key="value">
        <input type="hidden" name="{{ $name }}[]" :value="value">
    </template>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute z-modal mt-1 w-full rounded-card border border-gray-200 bg-white py-1 shadow-overlay"
         role="listbox">

        @if ($searchable)
            <div class="border-b border-gray-100 px-2 pb-2 pt-1">
                <x-form.input x-model="search" size="sm" icon="magnifying-glass"
                              placeholder="Cari…" x-ref="search" />
            </div>
        @endif

        <div class="scrollbar-thin max-h-56 overflow-y-auto py-1">
            <template x-for="option in filtered" :key="option.value">
                <label class="flex cursor-pointer items-center gap-2.5 px-3 py-1.5 text-sm transition hover:bg-gray-50">
                    <input type="checkbox"
                           class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                           :checked="isSelected(option.value)"
                           @change="toggle(option.value)">
                    <span class="min-w-0 flex-1 truncate text-gray-700" x-text="option.label"></span>
                </label>
            </template>

            <template x-if="filtered.length === 0">
                <p class="px-3 py-3 text-center text-sm text-gray-400">{{ $emptyText }}</p>
            </template>
        </div>

        <div class="flex items-center justify-between border-t border-gray-100 px-3 pb-1 pt-2">
            <span class="text-xs text-gray-400">
                <span x-text="selected.length"></span> dipilih
            </span>
            <button type="button" @click="clear()"
                    class="rounded text-xs text-gray-500 transition hover:text-danger-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                Kosongkan
            </button>
        </div>
    </div>
</div>
