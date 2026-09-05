@props([
    'show' => 'false',
    'title' => 'Konfirmasi',
    'confirmLabel' => 'Hapus',
    'cancelLabel' => 'Batal',
    'variant' => 'danger',
    // Alpine expression run on confirm.
    'onConfirm' => '',
    // Alpine statement run on Cancel (and passed through to the base modal
    // component for Escape/backdrop/X). See the prop doc on modal.blade.php —
    // required
    // whenever `show` is a computed expression rather than a bare variable,
    // which every delete-confirmation modal's "confirming !== null" is.
    'onClose' => null,
    'loading' => null,
])

@php
    $icons = [
        'danger' => ['icon' => 'exclamation-triangle', 'wrap' => 'bg-danger-100 text-danger-600'],
        'warning' => ['icon' => 'exclamation-circle', 'wrap' => 'bg-warning-100 text-warning-600'],
        'primary' => ['icon' => 'information-circle', 'wrap' => 'bg-primary-100 text-primary-600'],
    ];
    $v = $icons[$variant] ?? $icons['danger'];
    $close = $onClose ?: "{$show} = false";
@endphp

{{-- The only sanctioned confirmation for destructive actions — browser
     confirm() is forbidden (context.md §7.11). --}}
<x-modal :show="$show" :on-close="$onClose" size="sm" :title="null">
    <div class="flex gap-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $v['wrap'] }}">
            <x-icon :name="$v['icon']" class="h-5 w-5" />
        </span>

        <div class="min-w-0 pt-0.5">
            <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
            <div class="mt-1 text-sm text-gray-500">{{ $slot }}</div>
        </div>
    </div>

    <x-slot:footer>
        {{-- Blade directives cannot appear inside a component tag's attribute
             list, so conditional bindings are built here. --}}
        <x-button type="button" variant="secondary" @click="{{ $close }}"
                  :attributes="new \Illuminate\View\ComponentAttributeBag($loading ? [':disabled' => $loading] : [])">
            {{ $cancelLabel }}
        </x-button>

        <x-button type="button" :variant="$variant === 'primary' ? 'primary' : 'danger'"
                  @click="{{ $onConfirm }}"
                  :loading="$loading">
            {{ $confirmLabel }}
        </x-button>
    </x-slot:footer>
</x-modal>
