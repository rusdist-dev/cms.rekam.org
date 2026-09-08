# CMS Multi-Company Profile

Back-office tunggal untuk mengelola konten dua company profile (**rekam.org** dan
**perikanan.org**). Website compro adalah aplikasi terpisah yang mengonsumsi API publik CMS ini.

- Perencanaan, arsitektur, dan roadmap → [`plan.md`](plan.md)
- **Aturan keras implementasi** (wajib dibaca sebelum menulis kode) → [`context.md`](context.md)
- Deploy, hardening, backup, dan menambah tenant di produksi → [`docs/deploy.md`](docs/deploy.md)

## Stack

| | |
|---|---|
| Framework | Laravel 10 · PHP 8.1 |
| Auth & RBAC | Laravel Breeze (Blade) + `spatie/laravel-permission` |
| View | Blade (MPA) + Alpine.js 3 |
| Styling | Tailwind CSS 3 (`forms`, `typography`) |
| Chart | Chart.js 4 |
| HTTP client | `fetch()` bawaan browser — **axios dilarang** |
| Database | MySQL/MariaDB — 1 DB pusat + 1 DB per tenant |

## Arsitektur Database

Tiga database untuk dua company:

| Database | Isi | Koneksi |
|---|---|---|
| `cms_central` | user, role/permission, registri tenant, activity log, session/queue/cache | `mysql` |
| `cms_rekam` | seluruh konten Rekam | `tenant` |
| `cms_perikanan` | seluruh konten Perikanan | `tenant` |

Nama database tenant **tidak pernah** ditulis di `.env` atau di kode — satu-satunya sumber
kebenaran adalah tabel `tenants` di `cms_central`. Middleware `ResolveTenant` menimpa
`config('database.connections.tenant.database')` saat runtime.

## Setup Lokal

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Buat tiga database kosong:

```sql
CREATE DATABASE cms_central     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE cms_rekam       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE cms_perikanan   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Lalu jalankan migrasi dan seeder:

```bash
php artisan migrate           # DB pusat  (database/migrations/)
php artisan db:seed           # akun admin pertama (+ role & tenant mulai Fase 2)
php artisan tenants:migrate   # DB tenant (database/migrations/tenant/)
php artisan tenants:seed
```

### Akun Admin

Tidak ada halaman registrasi — CMS ini back-office, akun dibuat administrator.

`php artisan db:seed` membuat akun pertama dari `ADMIN_NAME` / `ADMIN_EMAIL` /
`ADMIN_PASSWORD` di `.env`. Bila `ADMIN_PASSWORD` kosong atau kurang dari 8 karakter,
kata sandi acak dibuat dan **ditampilkan sekali** di terminal. Seeder ini aman diulang:
akun yang sudah ada tidak pernah ditimpa.

Menambah akun berikutnya, atau mereset kata sandi tanpa server email:

```bash
# kata sandi dibuat acak dan ditampilkan sekali
php artisan cms:user --name="Budi Santoso" --email=budi@rekam.org

# tentukan sendiri kata sandinya
php artisan cms:user --name="Budi Santoso" --email=budi@rekam.org --password=rahasia-sekali

# perbarui nama/kata sandi akun yang sudah ada
php artisan cms:user --email=budi@rekam.org --password=sandi-baru --force
```

Tanpa opsi, perintah ini menanyakan nama dan email secara interaktif.

### Peran & Izin

Empat peran dibuat `RolePermissionSeeder`. Izin berformat `{modul}.{aksi}`
(`news.view`, `news.publish`, `team.reorder`, dst.) dan seluruhnya dapat diatur
ulang di halaman **Peran & Izin**.

| Peran | Cakupan |
|---|---|
| `super-admin` | Seluruh izin, dan melewati setiap pengecekan izin. Melihat semua company tanpa perlu ditugaskan. |
| `admin` | Semua kecuali kelola company dan definisi peran — dua tuas yang bisa mengunci organisasi dari CMS-nya sendiri. |
| `editor` | Tulis dan susun konten. Menerbitkan dan menghapus tetap milik admin. |
| `viewer` | Baca saja, tanpa akses ke pengguna/peran/company. |

Pengaman yang tidak bisa dilanggar lewat UI maupun API: super admin terakhir tidak
dapat diturunkan atau dinonaktifkan, akun tidak dapat menghapus dirinya sendiri,
admin tidak dapat mengubah super admin, dan peran `super-admin` tidak dapat diedit
atau diduplikasi.

Menonaktifkan akun (`is_active`) langsung berlaku pada request berikutnya, tidak
menunggu sesi berakhir.

Jalankan aplikasi (dua terminal):

```bash
npm run dev
php artisan serve
```

## Perintah Tenant

| Perintah | Fungsi |
|---|---|
| `php artisan tenants:migrate` | Jalankan `tenant/shared/` ke semua tenant, lalu `tenant/{slug}/` ke tenant terkait |
| `php artisan tenants:migrate --tenant=rekam` | Batasi ke satu tenant |
| `php artisan tenants:migrate --fresh` | Drop lalu migrasi ulang (hanya untuk lokal) |
| `php artisan tenants:status` | Cek migrasi yang tertinggal per tenant |
| `php artisan tenants:seed` | Seed pengaturan & taksonomi per tenant |
| `php artisan tenants:migrate --seed` | Migrasi lalu seed sekaligus |

Migrasi tenant **tidak boleh** ditaruh di `database/migrations/` — lihat `context.md` §5.5.

Struktur folder migrasi tenant:

```
database/migrations/tenant/
├── shared/       → semua tenant   (site_settings, media, …)
├── rekam/        → hanya rekam    (units)
└── perikanan/    → hanya perikanan (milestones)
```

Tiap database tenant punya tabel `migrations` sendiri, jadi riwayat dan isi skema
kedua company boleh berbeda. Seeder khusus tenant memakai pola yang sama:
`database/seeders/Tenant/{Slug}/{Slug}Seeder.php` dijalankan otomatis setelah
seeder bersama.

**Menambah company baru:**

```sql
CREATE DATABASE cms_namabaru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Lalu daftarkan di `TenantSeeder`, jalankan `php artisan db:seed`, dan
`php artisan tenants:migrate --tenant=namabaru --seed`.

## Catatan Lingkungan

**MariaDB 10.4 vs MySQL 8** — `plan.md` §4 menyebut MySQL 8. Lingkungan pengembangan saat ini
memakai MariaDB 10.4 (XAMPP). Kolom JSON, `JSON_CONTAINS`, dan generated column tetap didukung,
jadi skema dan query ditulis kompatibel untuk keduanya. Yang perlu diperhatikan:

- MariaDB menyimpan `json` sebagai `longtext` dengan CHECK constraint, bukan tipe JSON asli.
  Ini transparan untuk Eloquent (`$casts = ['title' => 'array']`).
- Index pencarian judul memakai generated column atas `title->'$.id'` — sintaksnya sama,
  tetapi MariaDB butuh generated column ber-tipe eksplisit sebelum di-index.

Bila nanti produksi memakai MySQL 8, tidak ada perubahan kode yang diperlukan.

## Struktur Penting

```
app/Http/Controllers/Dashboard/   controller yang me-render Blade (shell HTML saja)
app/Http/Controllers/DashApi/     internal JSON API  → /dash-api/v1/*   (guard web)
app/Http/Controllers/PublicApi/   API untuk compro   → /api/v1/{tenant}/* (X-Api-Key)
app/Models/Concerns/              TenantModel, HasTranslations, HasSortOrder
app/Services/                     TenantManager, TaxonomyService, MediaService, StatsService
database/migrations/              DB pusat
database/migrations/tenant/       shared/ · rekam/ · perikanan/
resources/js/api.js               window.api — satu-satunya jalan keluar untuk fetch()
resources/js/alpine/              komponen Alpine reusable (resourceTable, resourceForm, ...)
resources/views/components/       komponen Blade
```

## Sebelum Commit

```bash
./vendor/bin/pint
php artisan test
npm run build
```

Checklist kelengkapan fitur ada di `context.md` §9 (Definition of Done).
