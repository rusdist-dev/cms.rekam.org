@props([
    'cols' => 4,
    'rows' => 8,
])

{{-- The `loading` state of the four mandatory states (context.md §2.3).
     Column count matches the real table so the layout does not jump. --}}
@for ($r = 0; $r < $rows; $r++)
    <tr>
        @for ($c = 0; $c < $cols; $c++)
            <td class="px-4 py-3">
                <div class="skeleton h-4 {{ $c === 0 ? 'w-3/4' : ($c === $cols - 1 ? 'w-10' : 'w-1/2') }}"></div>
            </td>
        @endfor
    </tr>
@endfor
