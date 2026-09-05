@props([
    // Alpine expression pointing at the form model, used to flag a locale whose
    // required fields are still empty.
    'completeness' => null,
])

@php
    $locales = config('cms.locales');
    $labels = config('cms.locale_labels');
    $default = config('cms.default_locale');
@endphp

{{-- ID/EN inside one form, one submit (context.md §6.3). ID is mandatory, EN
     optional with a fallback in the public API. --}}
<div x-data="{ locale: '{{ $default }}' }" {{ $attributes }}>
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-1" role="tablist" aria-label="Bahasa konten">
            @foreach ($locales as $locale)
                <button type="button"
                        role="tab"
                        id="lang-tab-{{ $locale }}"
                        aria-controls="lang-panel-{{ $locale }}"
                        :aria-selected="locale === '{{ $locale }}' ? 'true' : 'false'"
                        @click="locale = '{{ $locale }}'"
                        :class="locale === '{{ $locale }}'
                            ? 'border-primary-600 text-primary-700'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                        class="flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">

                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] uppercase">{{ $locale }}</span>
                    {{ $labels[$locale] ?? strtoupper($locale) }}

                    @if ($locale === $default)
                        <span class="text-danger-600" aria-hidden="true">*</span>
                        <span class="sr-only">(wajib diisi)</span>
                    @elseif ($completeness)
                        {{-- A quiet dot, not a warning: EN really is optional. --}}
                        <span x-show="! ({{ $completeness }})['{{ $locale }}']" x-cloak
                              class="h-1.5 w-1.5 rounded-full bg-warning-400"
                              title="Terjemahan belum lengkap"></span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    <div class="pt-5">
        {{ $slot }}
    </div>
</div>
