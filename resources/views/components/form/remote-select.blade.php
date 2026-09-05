@props([
    'name',
    'endpoint',
    // Alpine expression the selection is bound to, e.g. "form.category_id".
    // Nested x-data inherits the parent scope, so the bare name resolves up —
    // $root is a DOM element and would not.
    'model',
    'placeholder' => 'Pilih…',
    'emptyText' => 'Belum ada pilihan.',
    'errorText' => 'Gagal memuat pilihan.',
    // Dot paths into each row: taxonomy returns {value,label}, relational
    // records return {id, name:{id,en}}.
    'valueKey' => 'value',
    'labelKey' => 'label',
    'size' => 'md',
    'alpine' => false,
    'id' => null,
])

@php
    $id ??= $name;

    // Same base classes as the static form.select component, so a
    // remote-backed dropdown looks identical to a static one — a filter row
    // must not visibly betray which of its selects fetches its options over
    // the network.
    $sizes = ['sm' => 'py-1.5 text-sm', 'md' => 'py-2 text-sm', 'lg' => 'py-2.5 text-base'];

    $base = implode(' ', [
        'block w-full rounded border-gray-300 pe-9 ps-3 text-gray-900 shadow-sm transition',
        'focus:border-primary-500 focus:ring-primary-500',
        'disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500',
        $sizes[$size] ?? $sizes['md'],
    ]);

    $errorClasses = 'border-danger-400 focus:border-danger-500 focus:ring-danger-500';

    // :class is a distinct attribute from class (it compiles to x-bind:class),
    // and Alpine merges string bindings additively via classList.add rather
    // than replacing the attribute — so this coexists safely with $base below.
    // What must never happen is two literal class="..." attributes on the same
    // tag: the HTML parser silently keeps only the first and drops the rest.
    $alpineAttrs = $alpine ? [
        ':class' => "fieldError('{$name}') ? '{$errorClasses}' : ''",
        ':aria-invalid' => "fieldError('{$name}') ? 'true' : 'false'",
    ] : [];

    // A fresh bag for the <select>: the outer $attributes already belongs to
    // the wrapper <div> below (so a caller's extra class lands on the
    // component's root, same as every other form component). Reusing it here
    // would double up whatever the caller passed.
    $selectAttributes = (new \Illuminate\View\ComponentAttributeBag(['class' => $base]))->merge($alpineAttrs);
@endphp

<div x-data="remoteSelect('{{ $endpoint }}', { valueKey: '{{ $valueKey }}', labelKey: '{{ $labelKey }}' })"
     {{ $attributes }}>

    <select id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            x-model="{{ $model }}"
            :disabled="loading"
            {{ $selectAttributes }}>

        {{-- The placeholder doubles as the loading label, so the control never
             renders as an empty box while the request is in flight. --}}
        <option value="" x-text="loading ? 'Memuat…' : '{{ $placeholder }}'"></option>

        <template x-for="option in options" :key="option.value">
            <option :value="option.value" x-text="option.label"></option>
        </template>
    </select>

    {{-- error: says what failed and offers the retry (context.md §2.3). --}}
    <p x-show="error" x-cloak role="alert" class="mt-1.5 flex items-center gap-1.5 text-xs text-danger-600">
        <x-icon name="exclamation-circle" variant="solid" class="h-3.5 w-3.5 shrink-0" />
        <span>{{ $errorText }}</span>
        <button type="button" @click="load()"
                class="rounded underline transition hover:text-danger-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500">
            Coba lagi
        </button>
    </p>

    {{-- empty: a working request that returned nothing is not an error. --}}
    <p x-show="isBlank" x-cloak class="mt-1.5 text-xs text-gray-500">{{ $emptyText }}</p>
</div>
