@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconRight' => null,
    'href' => null,
    'type' => 'button',
    'loading' => null,
    'block' => false,
])

@php
    // context.md §7.3: primary for the main action, danger for destructive.
    // A new look is a new `variant` value here — never a new component.
    $variants = [
        'primary' => 'bg-primary-600 text-white shadow-sm hover:bg-primary-700 focus-visible:ring-primary-500 disabled:bg-primary-300',
        'secondary' => 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 shadow-sm hover:bg-gray-50 focus-visible:ring-primary-500 disabled:text-gray-400',
        'danger' => 'bg-danger-600 text-white shadow-sm hover:bg-danger-700 focus-visible:ring-danger-500 disabled:bg-danger-300',
        'warning' => 'bg-warning-500 text-white shadow-sm hover:bg-warning-600 focus-visible:ring-warning-400 disabled:bg-warning-300',
        'ghost' => 'bg-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-primary-500 disabled:text-gray-400',
        'ghost-danger' => 'bg-transparent text-danger-600 hover:bg-danger-50 focus-visible:ring-danger-500 disabled:text-danger-300',
        'link' => 'bg-transparent text-primary-600 underline-offset-4 hover:text-primary-700 hover:underline focus-visible:ring-primary-500 disabled:text-gray-400',
    ];

    $sizes = [
        'xs' => 'gap-1 px-2 py-1 text-xs',
        'sm' => 'gap-1.5 px-2.5 py-1.5 text-sm',
        'md' => 'gap-2 px-3.5 py-2 text-sm',
        'lg' => 'gap-2 px-4 py-2.5 text-base',
    ];

    $iconSizes = ['xs' => 'w-3.5 h-3.5', 'sm' => 'w-4 h-4', 'md' => 'w-5 h-5', 'lg' => 'w-5 h-5'];
    $iconSize = $iconSizes[$size] ?? $iconSizes['md'];

    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded font-medium transition',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        'disabled:cursor-not-allowed',
        $block ? 'w-full' : '',
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['primary'],
    ]);

    // `loading` names an Alpine expression, so the spinner, the label swap and
    // the disabled state stay in sync without every caller wiring three bindings
    // (context.md §2.6 — anti double-submit).
    $alpine = $loading
        ? [':disabled' => $loading, ':aria-busy' => $loading]
        : [];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" :class="$iconSize" />
        @endif
        {{ $slot }}
        @if ($iconRight)
            <x-icon :name="$iconRight" :class="$iconSize" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes])->merge($alpine) }}>
        @if ($loading)
            <x-spinner :size="$size === 'lg' ? 'md' : 'sm'" x-show="{{ $loading }}" x-cloak />
        @endif

        @if ($icon)
            {{-- Blade directives cannot sit inside a component tag's attribute
                 list, so the conditional binding is built as an attribute bag. --}}
            <x-icon :name="$icon" :class="$iconSize"
                    :attributes="new \Illuminate\View\ComponentAttributeBag($loading ? ['x-show' => '! ('.$loading.')'] : [])" />
        @endif

        {{ $slot }}

        @if ($iconRight)
            <x-icon :name="$iconRight" :class="$iconSize" />
        @endif
    </button>
@endif
