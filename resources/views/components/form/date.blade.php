@props([
    'name' => null,
    // date | datetime-local | time
    'type' => 'date',
    'alpine' => false,
    'id' => null,
])

{{-- Native pickers: no extra JS date library in the baseline (context.md §8.9),
     and the browser already localises the calendar. --}}
<x-form.input :name="$name" :type="$type" :alpine="$alpine" :id="$id"
              icon="{{ $type === 'time' ? 'clock' : 'calendar' }}"
              {{ $attributes }} />
