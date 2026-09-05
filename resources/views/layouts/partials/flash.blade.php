@php
    $flashes = array_filter([
        'success' => session('success'),
        'warning' => session('warning'),
        'danger' => session('error') ?? session('danger'),
        'info' => session('info') ?? session('status'),
    ]);
@endphp

@if ($flashes)
    <div class="mb-6 space-y-3">
        @foreach ($flashes as $variant => $message)
            <x-alert :variant="$variant" dismissible>{{ $message }}</x-alert>
        @endforeach
    </div>
@endif
