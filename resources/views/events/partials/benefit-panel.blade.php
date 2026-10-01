<x-card title="Benefit Acara"
        subtitle="Disimpan bersama event dalam satu kali simpan — tidak ada tombol simpan terpisah."
        icon="sparkles">

    <x-form.repeater model="form.benefits"
                     :blank="\App\Support\FormDefaults::benefitRow()"
                     add-label="Tambah benefit"
                     empty-title="Belum ada benefit"
                     empty-description="Tambahkan poin-poin benefit yang didapat peserta.">

        <div class="grid grid-cols-1 gap-3">
            <div>
                <label :for="'benefit-title-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                    Benefit (ID)
                </label>
                <input type="text"
                       :id="'benefit-title-' + index"
                       x-model="row.title.id"
                       placeholder="Sertifikat keikutsertaan"
                       :class="fieldError('benefits.' + index + '.title.id') ? 'border-danger-400' : 'border-gray-300'"
                       class="block w-full rounded py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">

                {{-- Row-indexed 422 errors land on the row that caused them
                     (context.md §4.11). --}}
                <p x-show="fieldError('benefits.' + index + '.title.id')" x-cloak
                   x-text="fieldError('benefits.' + index + '.title.id')"
                   role="alert" class="mt-1 text-xs text-danger-600"></p>
            </div>

            <div>
                <label :for="'benefit-title-en-' + index" class="mb-1.5 block text-xs font-medium text-gray-600">
                    Benefit (EN)
                </label>
                <input type="text"
                       :id="'benefit-title-en-' + index"
                       x-model="row.title.en"
                       placeholder="Certificate of participation"
                       class="block w-full rounded border-gray-300 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>
        </div>
    </x-form.repeater>

    <x-slot:footer>
        <p class="text-xs text-gray-500">
            Urutan bisa diatur dengan menyeret baris.
        </p>
    </x-slot:footer>
</x-card>
