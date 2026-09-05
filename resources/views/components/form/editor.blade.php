@props([
    'name' => null,
    'model' => null,
    'placeholder' => 'Tulis isi konten di sini…',
    'minHeight' => 'min-h-[16rem]',
    'id' => null,
])

@php
    $id ??= $name;

    $groups = [
        [
            ['cmd' => 'bold', 'icon' => null, 'label' => 'Tebal', 'text' => 'B', 'state' => 'bold'],
            ['cmd' => 'italic', 'icon' => null, 'label' => 'Miring', 'text' => 'I', 'state' => 'italic'],
        ],
        [
            ['cmd' => 'h2', 'icon' => null, 'label' => 'Judul 2', 'text' => 'H2'],
            ['cmd' => 'h3', 'icon' => null, 'label' => 'Judul 3', 'text' => 'H3'],
            ['cmd' => 'paragraph', 'icon' => null, 'label' => 'Paragraf', 'text' => 'P'],
        ],
        [
            ['cmd' => 'ul', 'icon' => 'list-bullet', 'label' => 'Daftar Butir'],
            ['cmd' => 'ol', 'icon' => 'queue-list', 'label' => 'Daftar Nomor'],
            ['cmd' => 'quote', 'icon' => null, 'label' => 'Kutipan', 'text' => '❝'],
        ],
    ];
@endphp

<div x-data="editor({{ $model ? "($model ?? '')" : "''" }})"
     @if ($model) x-modelable="html" x-model="{{ $model }}" @endif
     {{ $attributes->merge(['class' => 'overflow-hidden rounded border border-gray-300 bg-white shadow-sm focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500']) }}>

    <div class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 bg-gray-50 px-2 py-1.5"
         role="toolbar" aria-label="Format teks">

        @foreach ($groups as $group)
            @foreach ($group as $btn)
                <button type="button"
                        @click="run('{{ $btn['cmd'] }}')"
                        @if (! empty($btn['state'])) :aria-pressed="isActive('{{ $btn['state'] }}') ? 'true' : 'false'" @endif
                        title="{{ $btn['label'] }}"
                        aria-label="{{ $btn['label'] }}"
                        class="flex h-7 min-w-[1.75rem] items-center justify-center rounded px-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-200 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 aria-pressed:bg-gray-200 aria-pressed:text-gray-900">
                    @if (! empty($btn['icon']))
                        <x-icon :name="$btn['icon']" class="h-4 w-4" />
                    @else
                        {{ $btn['text'] }}
                    @endif
                </button>
            @endforeach

            @if (! $loop->last)
                <span class="mx-1 h-5 w-px bg-gray-300" aria-hidden="true"></span>
            @endif
        @endforeach

        <span class="mx-1 h-5 w-px bg-gray-300" aria-hidden="true"></span>

        <button type="button" @click="link()" title="Sisipkan Tautan" aria-label="Sisipkan Tautan"
                class="flex h-7 min-w-[1.75rem] items-center justify-center rounded px-1.5 text-gray-600 transition hover:bg-gray-200 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            <x-icon name="link" class="h-4 w-4" />
        </button>

        <button type="button" @click="run('clear')" title="Hapus Format" aria-label="Hapus Format"
                class="flex h-7 min-w-[1.75rem] items-center justify-center rounded px-1.5 text-gray-600 transition hover:bg-gray-200 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            <x-icon name="arrow-uturn-left" class="h-4 w-4" />
        </button>
    </div>

    <div class="relative">
        <div x-ref="area"
             @if ($id) id="{{ $id }}" @endif
             contenteditable="true"
             role="textbox"
             aria-multiline="true"
             @input="sync()"
             @blur="sync()"
             @paste="pastePlain($event)"
             class="prose prose-sm {{ $minHeight }} max-w-none px-4 py-3 focus:outline-none prose-headings:font-semibold prose-a:text-primary-600"></div>

        <p x-show="isEmpty" x-cloak
           class="pointer-events-none absolute left-4 top-3 text-sm text-gray-400">{{ $placeholder }}</p>
    </div>

    @if ($name)
        <input type="hidden" name="{{ $name }}" :value="html">
    @endif
</div>
