@props([
    // Alpine expression holding the rows (e.g. "form.rundowns").
    'model',
    'blank' => [],
    'addLabel' => 'Tambah baris',
    'emptyTitle' => 'Belum ada baris',
    'emptyDescription' => null,
    'sortable' => true,
])

{{-- Rows live in the parent form's model and are saved with it in one request
     (context.md §4.11) — there is no per-row endpoint. --}}
<div x-data="repeater({{ $model }} ?? [], @js($blank))"
     x-modelable="rows"
     x-model="{{ $model }}"
     {{ $attributes->merge(['class' => 'space-y-3']) }}>

    <template x-if="isEmpty">
        <div class="rounded-card border border-dashed border-gray-300 bg-gray-50">
            <x-empty-state :title="$emptyTitle" :description="$emptyDescription" icon="queue-list" size="sm">
                <x-button type="button" size="sm" variant="secondary" icon="plus" @click="add()">
                    {{ $addLabel }}
                </x-button>
            </x-empty-state>
        </div>
    </template>

    <div x-show="! isEmpty" class="space-y-2"
         @if ($sortable) x-sort="move($item, $position)" x-sort:config="{ handle: '[data-drag-handle]' }" @endif>

        {{-- Rows loaded from an API response (taxonomyEditor's options,
             resourceForm's form.rundowns) arrive through x-model/x-modelable,
             which bypasses repeater.js's withKey() — so row._key is undefined
             for every one of them. Falling back to index keeps each row's
             identity distinct so Alpine renders all of them instead of
             collapsing every same-key (undefined) row into one DOM element
             that just gets overwritten by the last item. --}}
        <template x-for="(row, index) in rows" :key="row._key ?? index">
            <div x-sort:item="index"
                 class="group relative flex items-start gap-2 rounded-card border border-gray-200 bg-white p-3 shadow-sm transition hover:border-gray-300">

                @if ($sortable)
                    <button type="button" data-drag-handle
                            class="mt-1.5 cursor-grab rounded p-1 text-gray-300 transition hover:bg-gray-100 hover:text-gray-500 active:cursor-grabbing focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            :aria-label="'Pindahkan baris ' + (index + 1)">
                        <x-icon name="arrows-up-down" class="h-4 w-4" />
                    </button>
                @endif

                <div class="min-w-0 flex-1">
                    {{ $slot }}
                </div>

                <div class="flex shrink-0 items-center gap-0.5">
                    <button type="button" @click="duplicate(index)"
                            class="rounded p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            :aria-label="'Duplikat baris ' + (index + 1)">
                        <x-icon name="rectangle-stack" class="h-4 w-4" />
                    </button>

                    <button type="button" @click="remove(index)"
                            class="rounded p-1.5 text-gray-400 transition hover:bg-danger-50 hover:text-danger-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500"
                            :aria-label="'Hapus baris ' + (index + 1)">
                        <x-icon name="trash" class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </template>
    </div>

    <div x-show="! isEmpty">
        <x-button type="button" size="sm" variant="secondary" icon="plus" @click="add()">
            {{ $addLabel }}
        </x-button>
    </div>
</div>
