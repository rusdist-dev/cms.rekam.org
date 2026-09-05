<x-card title="Identitas">
    <div class="space-y-5">
        <x-form.field name="name" label="Nama Company" required alpine>
            <x-form.input id="name" name="name" alpine x-model="form.name" />
        </x-form.field>

        <x-form.field name="domain" label="Domain Website"
                      hint="Domain compro yang mengonsumsi API company ini." alpine>
            <x-form.input id="domain" name="domain" alpine icon="globe-alt"
                          x-model="form.domain" placeholder="rekam.org" />
        </x-form.field>

        <x-form.toggle name="is_active" model="form.is_active"
                       label="Company aktif"
                       hint="Menonaktifkan menyembunyikan company dari tenant switcher." />
    </div>
</x-card>
