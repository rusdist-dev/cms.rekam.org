@props([
    // ['identitas' => 'Identitas', 'seo' => 'SEO']
    'tabs' => [],
    'active' => null,
    'model' => null,
    // Remembers the open tab across reloads. UI state, so localStorage
    // (context.md §1.5) — pass a unique key per page.
    'remember' => null,
])

@php
    $tabs = collect($tabs);
    $active ??= $tabs->keys()->first();
@endphp

<div x-data="{
        tab: '{{ $active }}',
        init() {
            @if ($remember)
                try {
                    const saved = localStorage.getItem('cms.tabs.{{ $remember }}')
                    if (saved && {{ Js::from($tabs->keys()) }}.includes(saved)) this.tab = saved
                } catch {}
                this.$watch('tab', (v) => { try { localStorage.setItem('cms.tabs.{{ $remember }}', v) } catch {} })
            @endif
        },
     }"
     @if ($model) x-modelable="tab" x-model="{{ $model }}" @endif
     {{ $attributes }}>

    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-1 overflow-x-auto" role="tablist" aria-label="Bagian">
            @foreach ($tabs as $key => $label)
                <button type="button"
                        role="tab"
                        id="tab-{{ $key }}"
                        aria-controls="panel-{{ $key }}"
                        :aria-selected="tab === '{{ $key }}' ? 'true' : 'false'"
                        :tabindex="tab === '{{ $key }}' ? 0 : -1"
                        @click="tab = '{{ $key }}'"
                        @keydown.right.prevent="$el.nextElementSibling?.focus()"
                        @keydown.left.prevent="$el.previousElementSibling?.focus()"
                        :class="tab === '{{ $key }}'
                            ? 'border-primary-600 text-primary-700'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="pt-5">
        {{ $slot }}
    </div>
</div>
