@props([
    'name' => null,
    'label' => null,
    'hint' => null,
    'checked' => false,
    'model' => null,
    'id' => null,
])

@php
    $id ??= $name;
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3']) }}
     x-data="{ on: {{ $model ? 'false' : ($checked ? 'true' : 'false') }} }"
     @if ($model) x-modelable="on" x-model="{{ $model }}" @endif>

    @if ($name)
        {{-- Unchecked boxes are not posted, so a 0 goes first for a real boolean. --}}
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="hidden" name="{{ $name }}" :value="on ? 1 : 0">
    @endif

    <button type="button"
            role="switch"
            @if ($id) id="{{ $id }}" @endif
            @click="on = ! on"
            :aria-checked="on ? 'true' : 'false'"
            @if ($label) aria-labelledby="{{ $id }}-label" @endif
            :class="on ? 'bg-primary-600' : 'bg-gray-200'"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
        <span aria-hidden="true"
              :class="on ? 'translate-x-5' : 'translate-x-0'"
              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition"></span>
    </button>

    @if ($label || $hint)
        <div class="min-w-0 text-sm">
            @if ($label)
                <span id="{{ $id }}-label" class="block font-medium text-gray-700">{{ $label }}</span>
            @endif
            @if ($hint)
                <span class="mt-0.5 block text-xs text-gray-500">{{ $hint }}</span>
            @endif
        </div>
    @endif
</div>
