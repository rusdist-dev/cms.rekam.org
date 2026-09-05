<x-card title="Hapus Akun" icon="exclamation-triangle"
        x-data="{ confirming: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">

    <p class="text-sm text-gray-600">
        Menghapus akun bersifat permanen. Seluruh data akun akan hilang dan tidak dapat dipulihkan.
        Unduh dulu apa pun yang masih Anda perlukan.
    </p>

    <div class="mt-5">
        <x-button type="button" variant="danger" icon="trash" @click="confirming = true">
            Hapus Akun Saya
        </x-button>
    </div>

    {{-- Password confirmation lives inside the modal, so the destructive path
         always goes through <x-modal.confirm>'s sibling (context.md §7.11). --}}
    <x-modal show="confirming" size="md" title="Hapus akun ini secara permanen?">
        <form method="POST" action="{{ route('profile.destroy') }}" id="delete-account-form" class="space-y-4">
            @csrf
            @method('DELETE')

            <p class="text-sm text-gray-600">
                Masukkan kata sandi Anda untuk mengonfirmasi. Tindakan ini tidak dapat dibatalkan.
            </p>

            <x-form.field name="password" label="Kata Sandi" required for="delete_password"
                          :error-bind="null">
                <x-form.input id="delete_password" name="password" type="password"
                              icon="lock-closed" required autocomplete="current-password" />
            </x-form.field>

            @if ($errors->userDeletion->isNotEmpty())
                <x-alert variant="danger">{{ $errors->userDeletion->first('password') }}</x-alert>
            @endif
        </form>

        <x-slot:footer>
            <x-button type="button" variant="secondary" @click="confirming = false">Batal</x-button>
            <x-button type="submit" variant="danger" icon="trash" form="delete-account-form">
                Hapus Akun
            </x-button>
        </x-slot:footer>
    </x-modal>
</x-card>
