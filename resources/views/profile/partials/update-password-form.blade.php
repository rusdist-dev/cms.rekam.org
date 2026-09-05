<x-card title="Kata Sandi" subtitle="Gunakan kata sandi yang panjang dan unik." icon="lock-closed">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <x-form.field name="current_password" label="Kata Sandi Saat Ini" required for="update_password_current_password">
            <x-form.input id="update_password_current_password" name="current_password" type="password"
                          icon="lock-closed" required autocomplete="current-password" />
        </x-form.field>

        <x-form.field name="password" label="Kata Sandi Baru" required
                      hint="Minimal 8 karakter." for="update_password_password">
            <x-form.input id="update_password_password" name="password" type="password"
                          icon="lock-closed" required autocomplete="new-password" />
        </x-form.field>

        <x-form.field name="password_confirmation" label="Ulangi Kata Sandi Baru" required
                      for="update_password_password_confirmation">
            <x-form.input id="update_password_password_confirmation" name="password_confirmation" type="password"
                          icon="lock-closed" required autocomplete="new-password" />
        </x-form.field>

        <div class="flex items-center gap-3">
            <x-button type="submit" icon="check">Simpan</x-button>

            @if (session('status') === 'password-updated')
                <p class="text-sm text-success-600" x-data="{ shown: true }" x-show="shown"
                   x-init="setTimeout(() => shown = false, 3000)">
                    Kata sandi diperbarui.
                </p>
            @endif
        </div>
    </form>
</x-card>
