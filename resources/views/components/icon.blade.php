@php
    $paths = $paths();

    // Tailwind resolves conflicting utilities by stylesheet order, not attribute
    // order — so merging a default "w-5 h-5" with a caller's "w-6" is a coin
    // flip. Only fall back to the default size when the caller sized it.
    $incoming = (string) $attributes->get('class');
    $sized = preg_match('/(^|\s)(w-|h-|size-)/', $incoming) === 1;
    $default = $sized ? 'shrink-0' : 'w-5 h-5 shrink-0';
@endphp

@if ($paths)
    <svg {{ $attributes->merge(['class' => $default]) }}
         xmlns="http://www.w3.org/2000/svg"
         viewBox="0 0 24 24"
         @if ($isSolid())
             fill="currentColor"
         @else
             fill="none" stroke="currentColor" stroke-width="1.5"
         @endif
         aria-hidden="true"
         focusable="false">
        {!! $paths !!}
    </svg>
@elseif (config('app.debug'))
    {{-- Fail loudly in development so a typo never ships as an invisible gap. --}}
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded bg-danger-100 px-1 font-mono text-[10px] text-danger-700']) }}
          title="Ikon '{{ $name }}' ({{ $variant }}) belum terdaftar di App\View\Components\Icon">?{{ $name }}</span>
@endif
