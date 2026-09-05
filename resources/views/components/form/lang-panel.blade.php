@props(['locale'])

<div x-show="locale === '{{ $locale }}'"
     x-cloak
     id="lang-panel-{{ $locale }}"
     role="tabpanel"
     aria-labelledby="lang-tab-{{ $locale }}"
     {{ $attributes->merge(['class' => 'space-y-5']) }}>
    {{ $slot }}
</div>
