<x-card title="Rundown Acara"
        subtitle="Disimpan bersama event dalam satu kali simpan — tidak ada tombol simpan terpisah."
        icon="queue-list">

    <x-form.repeater model="form.rundowns"
                     :blank="\App\Support\FormDefaults::rundownRow()"
                     add-label="Tambah sesi"
                     empty-title="Belum ada sesi"
                     empty-description="Tambahkan jam dan nama sesi untuk membentuk rundown acara.">

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
            <div class="sm:col-span-3">
                <label :for="'rundown-time-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                    Jam Mulai
                </label>
                <input type="time"
                       :id="'rundown-time-' + index"
                       x-model="row.time"
                       :class="fieldError('rundowns.' + index + '.time') ? 'border-danger-400' : 'border-gray-300'"
                       class="block w-full rounded py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">

                {{-- Row-indexed 422 errors land on the row that caused them
                     (context.md §4.11). --}}
                <p x-show="fieldError('rundowns.' + index + '.time')" x-cloak
                   x-text="fieldError('rundowns.' + index + '.time')"
                   role="alert" class="mt-1 text-xs text-danger-600"></p>
            </div>

            <div class="sm:col-span-5">
                <label :for="'rundown-title-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                    Nama Sesi (ID)
                </label>
                <input type="text"
                       :id="'rundown-title-' + index"
                       x-model="row.title.id"
                       placeholder="Pembukaan"
                       :class="fieldError('rundowns.' + index + '.title.id') ? 'border-danger-400' : 'border-gray-300'"
                       class="block w-full rounded py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">

                <p x-show="fieldError('rundowns.' + index + '.title.id')" x-cloak
                   x-text="fieldError('rundowns.' + index + '.title.id')"
                   role="alert" class="mt-1 text-xs text-danger-600"></p>
            </div>

            <div class="sm:col-span-4">
                <label :for="'rundown-title-en-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                    Nama Sesi (EN)
                </label>
                <input type="text"
                       :id="'rundown-title-en-' + index"
                       x-model="row.title.en"
                       placeholder="Opening"
                       class="block w-full rounded border-gray-300 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>

            <div class="sm:col-span-12">
                <label :for="'rundown-desc-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                    Keterangan (opsional)
                </label>
                <input type="text"
                       :id="'rundown-desc-' + index"
                       x-model="row.description.id"
                       placeholder="Pembicara, ruangan, atau catatan lain"
                       class="block w-full rounded border-gray-300 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>
        </div>
    </x-form.repeater>

    <x-slot:footer>
        <p class="text-xs text-gray-500">
            Durasi tiap sesi tersirat dari jam sesi berikutnya. Urutan bisa diatur dengan menyeret baris.
        </p>
    </x-slot:footer>
</x-card>
