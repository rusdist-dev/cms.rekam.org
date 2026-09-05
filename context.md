# context.md — Aturan Keras Proyek

Dokumen ini **mengikat**. Setiap kode yang masuk ke repo wajib patuh.
Kalau ada konflik antara dokumen ini dan preferensi pribadi/kebiasaan framework, **dokumen ini menang**.
Perencanaan & roadmap ada di `plan.md`.

Kata kunci: **WAJIB** = tidak boleh dilanggar. **DILARANG** = penolakan otomatis saat review.
**HINDARI** = boleh dengan alasan tertulis di PR.

---

## 1. Arsitektur Frontend — Blade, bukan SPA

1. **WAJIB** Laravel Blade sebagai satu-satunya view layer. Navigasi antar halaman = request HTTP
   biasa (MPA), bukan client-side router.
2. **DILARANG** React, Vue, Svelte, Inertia.js, Livewire, atau framework SPA/komponen JS apa pun.
3. **DILARANG** membangun halaman yang isinya hanya `<div id="app">` lalu dirender JS.
   Struktur halaman (layout, header, filter, kerangka tabel, form) **WAJIB** dirender server sebagai HTML.
4. **WAJIB** setiap URL bisa dibuka langsung / di-refresh / di-bookmark dan menghasilkan halaman utuh.
   Alpine hanya mengisi data ke dalam kerangka yang sudah ada.
5. **DILARANG** menyimpan state aplikasi di JS global. State milik server (session/DB);
   state UI (sidebar terbuka, tab aktif) milik Alpine + `localStorage`.
6. **DILARANG** build tool selain Vite. Semua JS/CSS lewat `resources/js/app.js` dan
   `resources/css/app.css` (`@vite`). **DILARANG** CDN `<script src="https://...">` di Blade.

---

## 2. HTTP Client — `fetch()` saja

1. **DILARANG** axios. **DILARANG** jQuery/`$.ajax`, superagent, ky, atau wrapper HTTP lain.
   `package.json` tidak boleh memuat paket-paket ini.
2. **WAJIB** `fetch()` bawaan browser untuk semua komunikasi ke API.
3. **WAJIB** setiap pengambilan data punya **empat state eksplisit** yang tampil di UI:
   `loading` (skeleton/spinner) · `error` (pesan + tombol "Coba lagi") · `empty` (empty-state) ·
   `ready` (data). Fetch tanpa loading state = ditolak.
4. **WAJIB** semua fetch lewat helper terpusat `window.api` (`resources/js/api.js`) yang mengurus
   header, CSRF, parsing JSON, dan normalisasi error. **DILARANG** memanggil `fetch()` mentah
   di dalam `x-data` inline.
5. **WAJIB** request tulis (POST/PUT/PATCH/DELETE) menyertakan header `X-CSRF-TOKEN`
   dan `Accept: application/json`.
6. **WAJIB** tombol yang memicu request dinonaktifkan selama request berjalan (anti double-submit).
7. **WAJIB** error 422 dipetakan ke pesan per-field di form; **DILARANG** hanya `alert()`
   atau menelan error diam-diam (`catch {}` kosong).

Pola kanonik helper (satu-satunya jalan keluar untuk request):

```js
// resources/js/api.js
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

async function request(method, url, body = null) {
    const res = await fetch(url, {
        method,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
            ...(method !== 'GET' ? { 'X-CSRF-TOKEN': csrf() } : {}),
        },
        body: body ? JSON.stringify(body) : null,
        credentials: 'same-origin',
    })

    if (res.status === 204) return null
    const data = await res.json().catch(() => null)

    if (!res.ok) {
        throw {
            status: res.status,
            message: data?.message ?? 'Terjadi kesalahan. Silakan coba lagi.',
            errors: data?.errors ?? {},   // 422 → error per field
        }
    }
    return data
}

window.api = {
    get: (url) => request('GET', url),
    post: (url, body) => request('POST', url, body),
    put: (url, body) => request('PUT', url, body),
    patch: (url, body) => request('PATCH', url, body),
    delete: (url) => request('DELETE', url),
}
```

Pola kanonik tabel (dipakai semua halaman index — **jangan tulis ulang per halaman**):

```js
// resources/js/alpine/resourceTable.js
export default (endpoint, initialFilters = {}) => ({
    items: [],
    meta: { current_page: 1, last_page: 1, total: 0 },
    filters: { search: '', page: 1, per_page: 15, ...initialFilters },
    loading: false,
    error: null,

    init() { this.load() },

    get isEmpty() { return !this.loading && !this.error && this.items.length === 0 },

    async load() {
        this.loading = true
        this.error = null
        try {
            const qs = new URLSearchParams(
                Object.entries(this.filters).filter(([, v]) => v !== '' && v !== null)
            )
            const res = await window.api.get(`${endpoint}?${qs}`)
            this.items = res.data
            this.meta = res.meta
        } catch (e) {
            this.error = e.message
            this.items = []
        } finally {
            this.loading = false
        }
    },

    applyFilters() { this.filters.page = 1; this.load() },
    goToPage(p) { this.filters.page = p; this.load() },
})
```

```blade
{{-- resources/views/news/index.blade.php --}}
<div x-data="resourceTable('{{ route('dash-api.news.index') }}', { status: '' })">
    <x-table.toolbar>
        <x-form.input x-model.debounce.400ms="filters.search" @input="applyFilters()"
                      placeholder="Cari berita..." icon="magnifying-glass" />
        <x-form.select x-model="filters.status" @change="applyFilters()" :options="$statuses" />
    </x-table.toolbar>

    <x-table :headers="['Judul', 'Kategori', 'Status', 'Tanggal', '']">
        <template x-if="loading"><x-table.skeleton :cols="5" :rows="8" /></template>

        <template x-if="error">
            <x-table.state colspan="5" variant="danger">
                <span x-text="error"></span>
                <x-button size="sm" variant="ghost" @click="load()">Coba lagi</x-button>
            </x-table.state>
        </template>

        <template x-if="isEmpty">
            <x-table.state colspan="5">
                <x-empty-state title="Belum ada berita" icon="newspaper" />
            </x-table.state>
        </template>

        <template x-for="item in items" :key="item.id">
            <tr>...</tr>
        </template>
    </x-table>

    <x-pagination />
</div>
```

---

## 3. Komponen — maksimalkan, jangan copy-paste

1. **WAJIB** setiap elemen UI yang muncul di **≥ 2 tempat** menjadi komponen Blade di
   `resources/views/components/`. Menyalin markup adalah pelanggaran.
2. **DILARANG** menulis `<button class="px-4 py-2 bg-teal-600 ...">` langsung di halaman.
   Gunakan `<x-button variant="primary">`. Hal yang sama untuk input, badge, card, tabel, modal.
3. **WAJIB** komponen menerima `{{ $attributes->merge([...]) }}` agar bisa diberi `x-model`,
   `@click`, `class` tambahan tanpa perlu varian baru.
4. **WAJIB** varian visual lewat **prop** (`variant`, `size`, `color`), bukan komponen baru.
   Contoh: `<x-badge variant="warning">`, bukan `<x-badge-warning>`.
5. **WAJIB** ikon hanya lewat `<x-icon name="..." class="w-5 h-5" />` (Heroicons).
   **DILARANG** menempel `<svg>` mentah di halaman, **DILARANG** font-icon (FontAwesome, dsb.).
6. **WAJIB** halaman (`resources/views/{modul}/*.blade.php`) tidak lebih dari **±150 baris**.
   Lebih dari itu → pecah menjadi komponen/partial.
7. **WAJIB** class component (`app/View/Components`) hanya bila butuh logika PHP;
   selain itu pakai anonymous component (file Blade saja).
8. **DILARANG** `<style>` inline dan file CSS custom per halaman. Semua styling dengan utility
   Tailwind di dalam komponen. `@apply` hanya diizinkan di `app.css` untuk token dasar.
9. **WAJIB** JS Alpine yang lebih dari ~5 baris hidup di `resources/js/alpine/*.js` dan
   didaftarkan via `Alpine.data(...)`. **DILARANG** blok `<script>` panjang di dalam Blade.

---

## 4. Backend — API-first

1. **WAJIB** semua data untuk tabel/daftar/detail/chart diambil Alpine dari **internal API**
   (`/dash-api/v1/*`), bukan dari variabel Blade hasil query controller.
2. Controller Blade (`app/Http/Controllers/Dashboard/*`) hanya boleh mengirim data **statis/konstan**
   (opsi select, judul halaman, permission flag). **DILARANG** mengirim koleksi konten paginasi ke view.
3. **WAJIB** setiap modul punya endpoint internal lengkap: `index`, `show`, `store`, `update`,
   `destroy` (+ `reorder`/`bulk` bila relevan). Operasi tulis dari form lewat `fetch()` ke endpoint ini.
4. **WAJIB** semua respons JSON memakai **API Resource** (`app/Http/Resources/`).
   **DILARANG** `return $model` atau `response()->json($query->get())` mentah.
5. **WAJIB** bentuk respons konsisten:
   - list → `{ "data": [...], "meta": { current_page, last_page, per_page, total } }`
   - item → `{ "data": {...} }`
   - error → `{ "message": "...", "errors": { "field": ["..."] } }`
6. **WAJIB** validasi di **Form Request** (`app/Http/Requests/`).
   **DILARANG** `$request->validate()` di dalam controller.
7. **WAJIB** otorisasi eksplisit: middleware `permission:` (spatie) di route + Policy di controller.
   Endpoint tanpa pengecekan otorisasi = ditolak.
8. **WAJIB** controller tipis. Logika multi-langkah (upload + resize + simpan, reorder batch,
   invalidasi cache) masuk ke `app/Services/`.
9. **DILARANG** query di dalam view/komponen Blade. **WAJIB** eager loading (`with()`) —
   tidak boleh ada N+1.
10. **WAJIB** API publik read-only, hanya menyajikan konten berstatus `published`.
    Satu-satunya endpoint tulis adalah `POST /contact` (dengan throttle + honeypot).
11. **WAJIB** data anak yang diedit bersama induknya (rundown event) dikirim dalam **satu request**
    bersama induk dan disimpan dalam satu transaksi (`sync`: buat/ubah/hapus baris sekaligus).
    **DILARANG** endpoint CRUD terpisah per baris rundown atau request per baris.
    Error validasi baris dikembalikan ber-index (`rundowns.0.time`) agar bisa ditampilkan
    di baris yang bersangkutan.

---

## 5. Multi-Tenant — 2 database terpisah

1. **WAJIB** model konten extend `TenantModel` (koneksi `tenant`).
   Model `User`, `Tenant`, role/permission memakai koneksi pusat (`mysql`).
2. **DILARANG** hardcode nama database atau `DB::connection('cms_rekam')` di mana pun.
   Nama DB hanya berasal dari tabel `tenants` (`rekam` → `cms_rekam`, `perikanan` → `cms_perikanan`).
3. **WAJIB** setiap route dashboard dan API publik melewati middleware resolusi tenant.
   Tanpa tenant aktif → exception, **bukan** fallback ke DB default.
4. **DILARANG** query lintas tenant dalam satu request (join antar-database).
   Butuh data agregat 2 company → loop per tenant di level Service.
5. **WAJIB** migrasi tenant ditaruh di `database/migrations/tenant/` dan dijalankan dengan
   `php artisan tenants:migrate`. **DILARANG** menaruh migrasi tenant di `database/migrations/`.
   - Skema **inti bersama** → `tenant/shared/` (masuk ke semua tenant).
   - Kebutuhan **khusus satu company** → `tenant/rekam/` atau `tenant/perikanan/`
     (hanya masuk ke tenant itu).
   - **DILARANG** menaruh perubahan khusus satu company di `tenant/shared/` lalu "dibiarkan
     kosong" di company lain. Beda kebutuhan = beda folder.
6. **WAJIB** modul yang tidak dimiliki semua company dijaga **feature flag** (`tenants.features`):
   route pakai middleware `feature:{nama}`, menu pakai `@feature('nama')`, dan endpoint API publik
   tidak terdaftar bila fitur nonaktif.
7. **DILARANG** kode bersama (model inti, komponen, API Resource, Service umum) mengasumsikan
   kolom/tabel khusus tenant selalu ada. Akses field khusus hanya di balik pengecekan
   `tenant()->hasFeature(...)`, dan API Resource menyertakannya dengan
   `$this->mergeWhen(tenant()->hasFeature('...'), [...])`.
8. **DILARANG** menyamakan paksa skema dua company (menambah kolom yang hanya dipakai satu company
   ke `shared/`) maupun mengecek nama/slug tenant di dalam logika bisnis
   (`if ($tenant->slug === 'rekam')`). Yang boleh dicek adalah **fitur**, bukan identitas tenant.
   Pengecualian satu-satunya: `TenantsMigrate` yang memang memetakan slug → folder migrasi.
9. **WAJIB** file upload dipisah per tenant: `storage/app/public/media/{tenant_slug}/...`.
10. **WAJIB** cache & session key mengandung ID tenant (`cache()->tags("tenant:{$id}")`).
    Cache yang tercampur antar tenant = bug data.
11. **WAJIB** setiap modul punya test isolasi: data tenant A tidak pernah terlihat saat tenant B aktif.
12. **DILARANG** menulis opsi taksonomi milik satu company sebagai enum/array konstan di kode —
    termasuk level tim (advisor/manager/officer), kategori publikasi, dan daftar program
    (`related_programs`). Opsi hidup di `site_settings` DB tenant; kode hanya menyimpan **slug**,
    label diambil dari settings. Menambah program baru = pekerjaan editor, bukan deploy.

---

## 6. Bahasa

1. **WAJIB** seluruh UI CMS **berbahasa Indonesia**: label, tombol, menu, pesan validasi,
   flash message, empty state, judul halaman. **DILARANG** campur Inggris di UI
   ("Simpan" bukan "Save", "Hapus" bukan "Delete").
2. **WAJIB** pesan validasi Indonesia via `lang/id/validation.php` dan `attributes` yang manusiawi.
   `config('app.locale') = 'id'`.
3. **WAJIB** konten (yang dikonsumsi compro) bilingual **ID + EN** pada kolom JSON translatable.
   Editor mengisinya lewat `<x-form.lang-tabs>` dalam satu form.
4. **WAJIB** bahasa **ID terisi** untuk semua konten; EN opsional dengan fallback ke ID di API publik.
5. **DILARANG** menambah bahasa untuk UI CMS (tidak ada language switcher di CMS).
6. **DILARANG** menyimpan teks konten sebagai string biasa pada kolom yang seharusnya translatable.
7. **WAJIB** kode berbahasa Inggris (nama variabel, class, kolom, route, commit message).
   Bahasa Indonesia hanya untuk teks yang dilihat pengguna.

---

## 7. Desain & Layout

### Warna (tailwind.config.js)

```js
colors: {
  primary: colors.teal,     // aksi utama, tautan, state aktif   → primary-600
  warning: colors.amber,    // peringatan, status draft/pending  → warning-500
  danger:  colors.red,      // hapus, error, validasi gagal      → danger-600
  gray:    colors.slate,    // teks, border, background
}
```

1. **DILARANG** warna hex mentah di markup (`bg-[#0d9488]`). Selalu lewat token
   (`bg-primary-600`, `text-danger-600`).
2. **DILARANG** memakai warna lain sebagai aksen utama (biru, ungu, hijau lain).
   Warna semantik lain hanya untuk status: `success` = `emerald` (khusus badge "Terbit").
3. Pemetaan wajib: tombol utama = `primary`; hapus/nonaktif = `danger`;
   draft/terjadwal/perlu perhatian = `warning`.

### Layout

4. **WAJIB** layout dashboard: **sidebar kiri yang expandable** — grup menu bisa buka/tutup,
   sidebar bisa di-collapse ke mode ikon saja, state disimpan di `localStorage`,
   di mobile menjadi drawer overlay. Semua dikendalikan Alpine.
5. **WAJIB** sidebar dibangun dari komponen `<x-sidebar>`, `<x-sidebar.group>`, `<x-sidebar.item>`
   dengan `active` otomatis berdasarkan route dan visibilitas mengikuti permission (`@can`).
6. **WAJIB** topbar memuat: breadcrumb, **tenant switcher**, indikator pesan kontak baru, user menu.
7. **WAJIB** setiap halaman memakai `<x-page-header title="..." :breadcrumbs="..." />`
   dan aksi utama di kanan atas.
8. **WAJIB** responsif: sidebar drawer < `lg`, tabel `overflow-x-auto` di mobile,
   grid form 1 kolom di mobile / 2–3 kolom di desktop.
9. **WAJIB** aksesibilitas dasar: setiap input punya `<label>`, tombol ikon punya `aria-label`,
   fokus terlihat (`focus:ring-primary-500`), modal menutup dengan `Esc`.
10. **WAJIB** chart hanya lewat `<x-chart type="line" :endpoint="..." />` (Chart.js), datanya
    di-fetch dengan loading state. **DILARANG** menanam data chart langsung di HTML
    atau memakai library chart lain.
11. **WAJIB** aksi destruktif memakai modal konfirmasi `<x-modal.confirm>` (varian `danger`).
    **DILARANG** `confirm()` bawaan browser.

---

## 8. Konvensi & Struktur

1. Nama file/class: `PascalCase` (class), `kebab-case.blade.php` (view & komponen),
   `snake_case` (kolom DB), `camelCase` (variabel JS/PHP).
2. Route name: `{modul}.{aksi}` (web), `dash-api.{modul}.{aksi}` (internal),
   `api.v1.{modul}.{aksi}` (publik). **WAJIB** pakai `route()`, **DILARANG** URL literal di Blade/JS.
3. Tabel DB plural, model singular, foreign key `{model}_id`.
4. **WAJIB** migrasi baru — **DILARANG** mengubah file migrasi yang sudah dijalankan di produksi.
5. **WAJIB** `$fillable` eksplisit di setiap model. **DILARANG** `$guarded = []`.
6. **WAJIB** Laravel Pint sebelum commit; **DILARANG** commit dengan error PHPStan/Pint.
7. **WAJIB** `.env` tidak masuk repo; setiap variabel baru ditambahkan ke `.env.example`.
8. Komentar hanya untuk menjelaskan **alasan**, bukan mengulang kode. Tulis dalam bahasa Inggris.
9. **DILARANG** menambah paket Composer/NPM baru tanpa alasan tertulis di PR.
   Baseline: laravel/framework, laravel/breeze, spatie/laravel-permission,
   spatie/laravel-activitylog, intervention/image, tailwindcss, alpinejs, @alpinejs/sort, chart.js.
10. **DILARANG** menaruh file sementara/eksperimen di repo. Bersihkan `dd()`, `dump()`,
    `console.log()` sebelum commit.

---

## 9. Definition of Done (checklist per fitur)

Sebuah fitur dianggap selesai **hanya bila semua** poin berikut terpenuhi:

- [ ] Markup memakai komponen yang ada; tidak ada duplikasi markup baru.
- [ ] Tidak ada CSS custom, tidak ada `<svg>` mentah, tidak ada warna hex mentah.
- [ ] Data diambil dari internal API lewat `window.api` + `fetch`; **tidak ada axios**.
- [ ] State `loading / error / empty / ready` tampil dan sudah dicoba manual (termasuk gagal jaringan).
- [ ] Validasi memakai Form Request; error 422 tampil inline per field.
- [ ] Otorisasi: middleware `permission:` + Policy; sudah diuji dengan role `editor` dan `viewer`.
- [ ] Model konten memakai koneksi `tenant`; ada test bahwa data tenant tidak bocor antar company.
- [ ] Migrasi berada di folder yang benar (`tenant/shared/` vs `tenant/{slug}/`); modul non-inti
      dijaga `feature:` dan `@feature`; tidak ada pengecekan slug tenant di logika bisnis.
- [ ] Kolom konten translatable terisi ID; form punya tab ID/EN.
- [ ] Endpoint API publik (bila ada) read-only, hanya `published`, mendukung `?lang`, dan ter-cache.
- [ ] Semua teks UI berbahasa Indonesia.
- [ ] Responsif di mobile, tablet, desktop; keyboard-navigable.
- [ ] Feature test lolos; `php artisan test` dan `npm run build` hijau; Pint bersih.
- [ ] Aksi destruktif memakai modal konfirmasi.
- [ ] Tidak ada N+1 (dicek dengan query log/debugbar).

---

## 10. Ringkasan Larangan

| ❌ Dilarang | ✅ Gantinya |
|---|---|
| axios / jQuery ajax | `window.api` + `fetch()` |
| React / Vue / Inertia / Livewire | Blade + Alpine.js |
| Markup tombol/input disalin | `<x-button>`, `<x-form.input>` |
| `<svg>` mentah / FontAwesome | `<x-icon name="...">` (Heroicons) |
| `bg-[#0d9488]`, CSS custom | `bg-primary-600` (token Tailwind) |
| Data konten dikirim dari controller ke view | Fetch dari `/dash-api/v1/*` |
| `return $model` di API | API Resource |
| `$request->validate()` di controller | Form Request |
| `DB::connection('cms_rekam')` | `TenantModel` + `ResolveTenant` |
| `if ($tenant->slug === 'rekam')` | `tenant()->hasFeature('...')` |
| Kolom khusus 1 company di `tenant/shared/` | `tenant/{slug}/` + feature flag |
| `confirm()` browser | `<x-modal.confirm>` |
| Fetch tanpa loading state | 4 state wajib: loading/error/empty/ready |
| Teks UI bahasa Inggris | Bahasa Indonesia |
| `$guarded = []` | `$fillable` eksplisit |
| CDN script di Blade | Vite (`@vite`) |
