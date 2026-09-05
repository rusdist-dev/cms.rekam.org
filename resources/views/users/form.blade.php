<x-app-layout :title="$title" :breadcrumbs="$breadcrumbs">
    <form x-data="resourceForm('{{ route('dash-api.users.index') }}', @js(\App\Support\FormDefaults::user()), {
              recordId: {{ $recordId ? (int) $recordId : 'null' }},
              redirectTo: '{{ route('users.index') }}',
          })"
          @submit.prevent="submit()">

        <x-page-header :title="$title" :back="route('users.index')">
            <x-slot:actions>
                <x-button type="button" variant="secondary" :href="route('users.index')">Batal</x-button>
                <x-button type="submit" icon="check" loading="saving">Simpan</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('shared.form-states')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="Data Akun">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-form.field name="name" label="Nama Lengkap" required alpine>
                            <x-form.input id="name" name="name" alpine x-model="form.name" />
                        </x-form.field>

                        <x-form.field name="email" label="Email" required
                                      hint="Dipakai untuk masuk ke CMS." alpine>
                            <x-form.input id="email" name="email" type="email" alpine
                                          icon="at-symbol" x-model="form.email" />
                        </x-form.field>
                    </div>
                </x-card>

                <x-card title="Kata Sandi" icon="lock-closed">
                    <p class="mb-5 text-sm text-gray-500" x-show="isEditing" x-cloak>
                        Kosongkan bila tidak ingin mengganti kata sandi.
                    </p>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-form.field name="password" label="Kata Sandi"
                                      hint="Minimal 8 karakter." alpine>
                            <x-form.input id="password" name="password" type="password" alpine
                                          icon="lock-closed" x-model="form.password"
                                          autocomplete="new-password" />
                        </x-form.field>

                        <x-form.field name="password_confirmation" label="Ulangi Kata Sandi" alpine>
                            <x-form.input id="password_confirmation" name="password_confirmation" type="password" alpine
                                          icon="lock-closed" x-model="form.password_confirmation"
                                          autocomplete="new-password" />
                        </x-form.field>
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Peran" icon="shield-check">
                    <div x-data="apiResource('{{ route('dash-api.roles.index') }}', [])">
                        <x-form.field name="role" label="Peran" required
                                      hint="Menentukan izin yang dimiliki pengguna." alpine>
                            <select id="role" x-model="form.role" :disabled="loading"
                                    :class="fieldError('role') ? 'border-danger-400' : 'border-gray-300'"
                                    class="block w-full rounded py-2 pe-9 ps-3 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-50">
                                <option value="">Pilih peran…</option>
                                <template x-for="role in (data ?? [])" :key="role.id">
                                    <option :value="role.name" x-text="role.label"></option>
                                </template>
                            </select>
                        </x-form.field>

                        <p x-show="error" x-cloak class="mt-1.5 text-xs text-danger-600">
                            Gagal memuat peran.
                            <button type="button" @click="load()" class="underline">Coba lagi</button>
                        </p>
                    </div>
                </x-card>

                <x-card title="Akses Company" icon="building-storefront">
                    <p class="mb-4 text-sm text-gray-500">
                        Pengguna hanya bisa berganti ke company yang dicentang.
                    </p>

                    <div class="space-y-3">
                        @foreach ($tenantOptions as $tenant)
                            <x-form.checkbox :value="$tenant['slug']"
                                             :label="$tenant['name']"
                                             :hint="$tenant['domain']"
                                             x-model="form.tenants" />
                        @endforeach
                    </div>
                </x-card>

                <x-card title="Status">
                    <x-form.toggle name="is_active" model="form.is_active"
                                   label="Akun aktif"
                                   hint="Menonaktifkan mencabut akses tanpa menghapus akun." />
                </x-card>
            </div>
        </div>
    </form>
</x-app-layout>
