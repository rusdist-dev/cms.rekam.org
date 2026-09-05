@props(['name'])

<div x-show="tab === '{{ $name }}'"
     x-cloak
     id="panel-{{ $name }}"
     role="tabpanel"
     aria-labelledby="tab-{{ $name }}"
     {{ $attributes }}>
    {{ $slot }}
</div>
