<x-card title="Biaya & Kuota" icon="banknotes">
    <div class="space-y-5">
        <x-form.field name="fee" label="Biaya (Rp)"
                      hint="Kosongkan atau isi 0 bila acara gratis." alpine>
            <x-form.input id="fee" name="fee" type="number" min="0" step="1000" alpine
                          x-model.number="form.fee" placeholder="0" />
        </x-form.field>

        <x-form.field name="fee_note.id" label="Keterangan Biaya"
                      hint="Contoh: gratis untuk mahasiswa." alpine>
            <x-form.input id="fee_note.id" name="fee_note.id" alpine
                          x-model="form.fee_note.id" />
        </x-form.field>

        <x-form.field name="quota" label="Kuota Peserta" alpine>
            <x-form.input id="quota" name="quota" type="number" min="0" alpine
                          x-model.number="form.quota" placeholder="Tanpa batas" />
        </x-form.field>

        {{-- plan.md §5.2.c: quota is informational; there is no registration
             module, so no seats are counted anywhere. --}}
        <x-alert variant="info">
            Kuota hanya ditampilkan di website. CMS ini tidak menghitung sisa kuota
            atau menyimpan daftar peserta.
        </x-alert>
    </div>
</x-card>
