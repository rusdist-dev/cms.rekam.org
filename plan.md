# Plan — CMS Multi-Company Profile

Dokumen perencanaan: keputusan arsitektur, struktur direktori, roadmap berfase, dan estimasi.
Aturan keras implementasi ada di `context.md` (wajib dibaca sebelum menulis kode).

---

## 1. Ringkasan Proyek

Satu aplikasi CMS (satu codebase, satu deployment) untuk mengelola konten **2 company profile**,
dengan **2 database terpisah**. CMS hanya sebagai back-office; website company profile (compro)
adalah aplikasi terpisah yang mengonsumsi **API publik** dari CMS ini.

| Aspek | Keputusan |
|---|---|
| Framework | Laravel 10 (PHP 8.1) |
| Auth & RBAC | Laravel Breeze (Blade) + `spatie/laravel-permission` |
| Database | MySQL 8 — 1 DB pusat + 1 DB per company |
| View | Laravel Blade (MPA, bukan SPA) |
| Styling | Tailwind CSS 3 + `@tailwindcss/forms` + `@tailwindcss/typography` |
| Interaksi | Alpine.js 3 (+ plugin `@alpinejs/sort` untuk drag & drop urutan) |
| Chart | Chart.js 4 (via komponen Blade `<x-chart>`) |
| Icon | Heroicons — komponen Blade `<x-icon name="..." />` |
| HTTP client | **`fetch()` bawaan browser** — axios dilarang |
| Bahasa UI CMS | Indonesia saja |
| Bahasa konten | Indonesia + Inggris (untuk konsumsi compro) |

---

## 2. Keputusan Arsitektur

### 2.1 Model Multi-Tenant (2 database terpisah)

Pola: **satu aplikasi, database terpisah per tenant**, koneksi ditentukan saat runtime.

**Total 3 database** (untuk 2 company):

| Database | Isi | Dipakai oleh |
|---|---|---|
| `cms_central` | user, role/permission, daftar tenant, activity log, session/queue/cache | seluruh aplikasi (koneksi `mysql`) |
| `cms_rekam` | seluruh konten Rekam | hanya saat tenant `rekam` aktif (koneksi `tenant`) |
| `cms_perikanan` | seluruh konten Perikanan | hanya saat tenant `perikanan` aktif (koneksi `tenant`) |

Pemisahan ini **disengaja** untuk mengantisipasi kebutuhan yang berbeda antar company — lihat §2.2.

**Registri tenant** (isi tabel `tenants`, satu-satunya sumber kebenaran nama DB):

| `slug` | `name` | `db_name` | `domain` (web compro) |
|---|---|---|---|
| `rekam` | Rekam | `cms_rekam` | `rekam.org` |
| `perikanan` | Perikanan | `cms_perikanan` | `perikanan.org` |

CMS ini sendiri berada di `cms.rekam.org` dan melayani **kedua** tenant lewat tenant switcher —
bukan satu CMS per domain.

```
DB PUSAT  cms_central          DB TENANT  cms_rekam         DB TENANT  cms_perikanan
------------------------       ----------------------       ----------------------
users                          news                         news
roles, permissions             news_categories              news_categories
model_has_roles                events                       events
tenants                        team_members                 team_members
tenant_user (pivot)            publications                 publications
activity_log                   partners                     partners
sessions, jobs, cache          contact_messages             contact_messages
                               site_settings                site_settings
                               media                        media
```

Alur resolusi tenant:

1. User login → sistem baca tenant aktif dari session (`current_tenant_id`).
2. Middleware `ResolveTenant` menimpa `config('database.connections.tenant.database')`
   dengan nama DB tenant, lalu `DB::purge('tenant')` + `DB::reconnect('tenant')`.
3. Semua model konten extend `App\Models\Concerns\TenantModel` yang memaksa
   `protected $connection = 'tenant'`.
4. Ganti tenant lewat **tenant switcher** di topbar (hanya tenant yang di-assign ke user).
   `super-admin` melihat semua tenant.

Konsekuensi yang harus dipatuhi: **tidak ada query konten tanpa tenant aktif.** Middleware
`ResolveTenant` melempar `TenantNotResolvedException` bila tenant belum ditentukan — ini
pengaman agar data dua company tidak pernah tercampur.

**Alternatif yang ditolak:** single DB + kolom `tenant_id` (risiko kebocoran data antar company
bila satu query lupa scope) dan dua deployment terpisah (duplikasi maintenance).

### 2.2 Divergensi Kebutuhan Antar Company

DB dipisah supaya tiap company boleh **berbeda skema dan berbeda modul aktif** tanpa mengganggu
yang lain. Tiga mekanisme yang menopang ini:

**a. Migrasi shared vs migrasi khusus tenant**

```
database/migrations/tenant/
├── shared/                     # dijalankan ke SEMUA tenant (skema inti bersama)
│   ├── 2026_09_03_000100_create_news_table.php
│   └── ...
├── rekam/                      # hanya untuk tenant slug "rekam"
│   └── 2026_10_10_000100_create_units_table.php
└── perikanan/                  # hanya untuk tenant slug "perikanan"
    └── 2026_10_12_000100_create_milestones_table.php
```

`php artisan tenants:migrate` menjalankan `shared/` ke semua tenant, lalu `{slug}/` hanya ke tenant
bersangkutan. Tabel `migrations` ada di masing-masing DB tenant, jadi riwayat migrasi tiap company
terpisah dan boleh berbeda isi.

**b. Feature flag per tenant** — kolom `tenants.features` (JSON) menyalakan/mematikan modul:

```json
{ "news": true, "news_programs": true, "events": true, "event_rundown": true,
  "team": true, "publications": false, "partners": true, "contacts": true,
  "units": true, "milestones": false }
```

- Sidebar hanya menampilkan modul yang aktif: `@feature('units')`.
- Route modul dilindungi middleware `feature:units` → 404 bila tenant tidak memilikinya.
- Endpoint API publik modul nonaktif tidak terdaftar untuk tenant tersebut.
- Flag juga dipakai untuk **bagian form**, bukan hanya modul: `event_rundown` menyalakan tab
  rundown di form event, `news_programs` menyalakan multi-select program di form berita.

**c. Pengaturan & label per tenant** — `site_settings` hidup di DB tenant, jadi identitas,
level tim, kategori berita/event/publikasi, dan daftar program boleh sama sekali berbeda antar
company tanpa perubahan kode. Inilah yang membuat rekam (level: advisor board → manager,
program: forest/urban/ocean) dan perikanan (level: advisor/manager/officer, 6 program kelautan)
bisa memakai tabel yang sama. Rinciannya di §4 dan §5.

**Konsekuensi yang harus dipatuhi:** kode bersama **tidak boleh mengasumsikan kolom/tabel khusus
tenant selalu ada**. Akses fitur khusus hanya di balik pengecekan feature flag, dan API Resource
hanya menyertakan field khusus bila fiturnya aktif (lihat `context.md` §5).

### 2.3 Bilingual (ID/EN)

Kolom **JSON translatable** — satu baris konten memuat kedua bahasa.

```php
// migration
$table->json('title');      // {"id":"Judul","en":"Title"}
$table->json('body');
$table->json('slug');       // slug per bahasa

// model
protected $casts = ['title' => 'array', 'body' => 'array', 'slug' => 'array'];
```

- Admin UI: komponen `<x-form.lang-tabs>` — tab **ID / EN** di dalam satu form, satu kali submit.
- API publik: `?lang=en` (default `id`) → API Resource meratakan JSON jadi string bahasa terpilih,
  dengan fallback ke `id` bila terjemahan kosong.
- Bahasa **ID wajib**, **EN opsional** (draft boleh tanpa EN; publish EN butuh judul EN).

**Alternatif yang ditolak:** tabel `*_translations` terpisah (join berlebih, form lebih rumit)
dan kolom kembar `title_id`/`title_en` (skema membengkak saat bahasa bertambah).

### 2.4 API-first di dalam CMS

Dua permukaan API dengan tujuan berbeda:

| | Internal API | API Publik |
|---|---|---|
| Prefix | `/dash-api/v1/*` | `/api/v1/{tenant}/*` |
| Guard | `web` (session + CSRF) | `api` + header `X-Api-Key` per tenant |
| Konsumen | Alpine.js di halaman Blade | Website compro |
| Operasi | Baca **dan** tulis (CRUD) | Read-only, hanya konten `published` |
| Response | JSON (API Resource) | JSON (API Resource) + cache |

Halaman Blade hanya mengirim **shell HTML** (layout, filter, tabel kosong + skeleton);
seluruh data tabel/detail diambil Alpine via `fetch()` ke internal API dengan state
`loading / error / empty / ready`. Form submit juga lewat `fetch()` (POST/PUT/DELETE JSON)
sehingga error validasi 422 dirender inline tanpa reload halaman.

### 2.5 Struktur Modul Konten

Ringkasan modul; kebutuhan per company dan pemetaan kolomnya ada di §5.

| Modul | Lingkup | Fitur khusus |
|---|---|---|
| Berita | inti | Kategori, **program terkait (multi)**, cover, status `draft/scheduled/published`, `published_at`, rich text |
| Events | inti | Tanggal, lokasi, kategori, **biaya**, **kuota**, cover, status, + **rundown** (repeater jam–acara) |
| Tim | inti | Foto, jabatan, profil, **level** (grup), **`sort_order` drag & drop di dalam level** |
| Publikasi | inti | Judul, deskripsi, kategori, file PDF, cover, `is_featured`, `sort_order` |
| Partner | inti | Logo, nama, `title`, URL, `sort_order` drag & drop |
| Kontak | inti | (a) Info kontak statis di `site_settings`; (b) inbox pesan dari form compro (baca/arsip/balas via mailto) |
| Pengaturan | inti | Identitas situs, sosial media, SEO default, embed peta, taksonomi (level/kategori/program), API key |
| **Milestone** | khusus `perikanan` | Judul, isi, gambar, tahun, `sort_order` — untuk timeline |
| **Unit** | khusus `rekam` | Nama, deskripsi, URL, domain, logo, `sort_order` |

---

## 3. Struktur Direktori

```
cms.rekam.org/
├── app/
│   ├── Console/Commands/
│   │   ├── TenantsMigrate.php            # php artisan tenants:migrate
│   │   ├── TenantsSeed.php
│   │   └── TenantsStatus.php             # cek migrasi tertinggal per tenant
│   ├── Exceptions/TenantNotResolvedException.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Dashboard/                # controller yang me-render Blade (shell)
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── NewsController.php
│   │   │   │   ├── EventController.php
│   │   │   │   ├── TeamMemberController.php
│   │   │   │   ├── PublicationController.php
│   │   │   │   ├── PartnerController.php
│   │   │   │   ├── ContactMessageController.php
│   │   │   │   ├── MilestoneController.php       # feature:milestones (perikanan)
│   │   │   │   ├── UnitController.php            # feature:units (rekam)
│   │   │   │   ├── SiteSettingController.php
│   │   │   │   ├── UserController.php
│   │   │   │   ├── RoleController.php
│   │   │   │   ├── TenantController.php          # kelola tenant + feature flag (super-admin)
│   │   │   │   └── TenantSwitchController.php
│   │   │   ├── DashApi/                  # internal JSON API (dipakai Alpine)
│   │   │   │   ├── NewsApiController.php
│   │   │   │   ├── EventApiController.php        # rundown disimpan bersama event
│   │   │   │   ├── TeamMemberApiController.php   # + reorder()
│   │   │   │   ├── PublicationApiController.php
│   │   │   │   ├── PartnerApiController.php      # + reorder()
│   │   │   │   ├── ContactMessageApiController.php
│   │   │   │   ├── MilestoneApiController.php    # feature:milestones
│   │   │   │   ├── UnitApiController.php         # feature:units, + reorder()
│   │   │   │   ├── MediaApiController.php
│   │   │   │   └── StatsApiController.php        # data Chart.js
│   │   │   └── PublicApi/                # API untuk web compro
│   │   │       ├── NewsController.php
│   │   │       ├── EventController.php    # detail menyertakan rundown
│   │   │       ├── TeamController.php
│   │   │       ├── PublicationController.php
│   │   │       ├── PartnerController.php
│   │   │       ├── MilestoneController.php
│   │   │       ├── UnitController.php
│   │   │       ├── ProgramController.php  # opsi related_programs
│   │   │       ├── SettingController.php
│   │   │       └── ContactController.php  # satu-satunya endpoint tulis (POST pesan)
│   │   ├── Middleware/
│   │   │   ├── ResolveTenant.php
│   │   │   ├── ResolvePublicTenant.php
│   │   │   ├── EnsureTenantFeature.php   # middleware feature:{nama} → 404 bila nonaktif
│   │   │   └── SetPublicLocale.php
│   │   ├── Requests/                     # Form Request per aksi
│   │   │   ├── News/{StoreNewsRequest,UpdateNewsRequest}.php
│   │   │   └── ...                       # per modul
│   │   └── Resources/                    # API Resource
│   │       ├── Dash/NewsResource.php
│   │       └── Public/NewsResource.php   # sudah "diratakan" per bahasa
│   ├── Models/
│   │   ├── Concerns/{TenantModel,HasTranslations,HasSortOrder}.php
│   │   ├── User.php, Tenant.php          # koneksi pusat
│   │   └── News.php, NewsCategory.php, Event.php, EventRundown.php,
│   │       TeamMember.php, Publication.php, Partner.php, ContactMessage.php,
│   │       Milestone.php, Unit.php, SiteSetting.php, Media.php   # koneksi tenant
│   ├── Policies/
│   ├── Services/
│   │   ├── TenantManager.php             # singleton: tenant aktif, switch, daftar tenant
│   │   ├── TaxonomyService.php           # opsi level tim / kategori / program dari site_settings
│   │   ├── MediaService.php              # upload, resize, hapus
│   │   └── StatsService.php
│   ├── Support/{Locale.php,SlugMaker.php}
│   └── View/Components/                  # class component bila butuh logika
│       ├── Icon.php
│       └── Chart.php
├── config/
│   ├── database.php                      # koneksi: mysql (pusat) + tenant (dinamis)
│   ├── cms.php                           # locale, status, limit upload, daftar feature
│   └── permission.php
├── database/
│   ├── migrations/                       # DB PUSAT
│   ├── migrations/tenant/                # DB TENANT (dijalankan tenants:migrate)
│   │   ├── shared/                       # → semua tenant (skema inti)
│   │   ├── rekam/                        # → hanya tenant rekam (units)
│   │   └── perikanan/                    # → hanya tenant perikanan (milestones)
│   ├── seeders/
│   │   ├── DatabaseSeeder.php            # role, permission, super-admin, tenants
│   │   └── Tenant/{SettingSeeder,NewsSeeder,TeamSeeder,...}.php
│   └── factories/
├── public/
├── resources/
│   ├── css/app.css
│   ├── js/
│   │   ├── app.js                        # Alpine + plugin + registrasi komponen
│   │   ├── api.js                        # helper fetch terpusat (window.api)
│   │   ├── chart.js                      # helper Chart.js (tema teal)
│   │   └── alpine/                       # data component reusable
│   │       ├── resourceTable.js          # fetch list + filter + paginate + state
│   │       ├── resourceForm.js           # submit fetch + error 422 inline
│   │       ├── sortableList.js
│   │       ├── repeater.js               # baris dinamis (rundown event)
│   │       └── mediaPicker.js
│   └── views/
│       ├── layouts/
│       │   ├── app.blade.php             # sidebar expandable + topbar
│       │   ├── guest.blade.php           # login
│       │   └── partials/{head,scripts,flash}.blade.php
│       ├── components/                   # ← inti "maksimalkan komponen"
│       │   ├── icon.blade.php
│       │   ├── button.blade.php   badge.blade.php    card.blade.php
│       │   ├── alert.blade.php    avatar.blade.php   spinner.blade.php
│       │   ├── breadcrumb.blade.php      page-header.blade.php
│       │   ├── empty-state.blade.php     stat-card.blade.php
│       │   ├── modal.blade.php    drawer.blade.php   dropdown.blade.php
│       │   ├── tabs.blade.php     pagination.blade.php    chart.blade.php
│       │   ├── sidebar/{index,item,group,brand}.blade.php
│       │   ├── topbar/{index,tenant-switcher,user-menu}.blade.php
│       │   ├── form/
│       │   │   ├── input.blade.php   textarea.blade.php   select.blade.php
│       │   │   ├── multi-select.blade.php  # related_programs
│       │   │   ├── repeater.blade.php      # rundown event
│       │   │   ├── editor.blade.php        # rich text
│       │   │   ├── image-upload.blade.php  file-upload.blade.php
│       │   │   ├── toggle.blade.php  checkbox.blade.php   date.blade.php
│       │   │   ├── tag-input.blade.php
│       │   │   ├── lang-tabs.blade.php     # tab ID/EN
│       │   │   ├── field.blade.php         # label + error + hint
│       │   │   └── error.blade.php
│       │   └── table/
│       │       ├── index.blade.php   th.blade.php   td.blade.php
│       │       ├── skeleton.blade.php      toolbar.blade.php
│       │       ├── state.blade.php         # baris error / empty
│       │       └── row-actions.blade.php
│       ├── dashboard/index.blade.php
│       ├── news/{index,form}.blade.php
│       ├── events/{index,form}.blade.php
│       ├── team/{index,form}.blade.php
│       ├── publications/{index,form}.blade.php
│       ├── partners/{index,form}.blade.php
│       ├── contacts/{index,show,settings}.blade.php
│       ├── milestones/{index,form}.blade.php     # feature:milestones
│       ├── units/{index,form}.blade.php          # feature:units
│       ├── settings/index.blade.php
│       ├── tenants/{index,form}.blade.php        # super-admin: flag & API key
│       ├── users/{index,form}.blade.php
│       ├── roles/{index,form}.blade.php
│       └── auth/login.blade.php
├── routes/
│   ├── web.php          # halaman Blade
│   ├── dash-api.php     # internal API (guard web)
│   └── api.php          # API publik (X-Api-Key)
├── tests/{Feature,Unit}/
├── storage/app/public/  # media per tenant: media/{tenant_slug}/...
├── tailwind.config.js
├── vite.config.js
├── context.md           # ATURAN KERAS
└── plan.md
```

---

## 4. Skema Database (ringkas)

### DB Pusat — `cms_central`

```
tenants          id, name, slug, db_name, domain, api_key, features(json), is_active, timestamps
users            id, name, email, password, avatar, is_active, timestamps
tenant_user      tenant_id, user_id
roles / permissions / model_has_roles / role_has_permissions      (spatie)
activity_log     (spatie/laravel-activitylog)
sessions, jobs, failed_jobs, cache
```

### DB Tenant — skema inti (`migrations/tenant/shared/`)

Dibuat di **`cms_rekam` dan `cms_perikanan`**. Pemetaan ke kebutuhan tiap company ada di §5.

```
news_categories   id, name(json), slug(json), sort_order
news              id, category_id, title(json), slug(json), excerpt(json), body(json),
                  cover_path, related_programs(json), status, published_at, author_name,
                  views, meta_title(json), meta_description(json), timestamps, softDeletes
events            id, title(json), slug(json), description(json), category, location(json),
                  cover_path, start_at, end_at, is_all_day, fee, fee_note(json), quota,
                  registration_url, status, timestamps, softDeletes
event_rundowns    id, event_id, time, title(json), description(json), sort_order, timestamps
team_members      id, name, slug, position(json), bio(json), photo_path, group,
                  email, socials(json), sort_order, is_active, timestamps
publications      id, title(json), description(json), category, file_path, cover_path,
                  is_featured, sort_order, timestamps
partners          id, name, title(json), logo_path, url, sort_order, is_active, timestamps
contact_messages  id, name, email, phone, subject, message, status, ip, timestamps
site_settings     id, group, key, value(json)            # unique(group, key)
media             id, disk, path, filename, mime, size, width, height,
                  alt(json), uploaded_by, timestamps
```

### DB Tenant — tabel khusus

```
migrations/tenant/rekam/
units             id, name, description(json), url, domain, logo_path,
                  sort_order, is_active, timestamps

migrations/tenant/perikanan/
milestones        id, title(json), body(json), cover_path, year,
                  sort_order, is_active, timestamps
```

### Taksonomi tersimpan sebagai data, bukan enum

Semua daftar opsi yang berbeda antar company tinggal di `site_settings` (DB tenant), sehingga
menambah level tim atau program adalah pekerjaan editor — bukan deploy:

```
group   key        value (json)
------  ---------  ------------------------------------------------------------------
team    levels     [{"slug":"advisor-board","label":{"id":"Dewan Penasihat",
                     "en":"Advisor Board"}}, ...]              # urutan = urutan tampil
news    programs   [{"slug":"forest","label":{"id":"Forest","en":"Forest"}}, ...]
news    categories # (relasional di tabel news_categories, bukan settings)
event   categories [{"slug":"workshop","label":{...}}, ...]
pub     categories [{"slug":"laporan","label":{...}}, ...]
site    identity   {"name":"...","tagline":{...},"email":"...","phone":"...","address":{...}}
site    socials    {"instagram":"...","linkedin":"...","youtube":"..."}
site    seo        {"meta_title":{...},"meta_description":{...},"og_image":"..."}
```

Kolom konten hanya menyimpan **slug** (`news.related_programs = ["forest","ocean"]`,
`team_members.group = "director"`); label diambil dari settings sesuai bahasa.

Indeks penting: `news(status, published_at)`, `events(status, start_at)`,
`event_rundowns(event_id, sort_order)`, `team_members(is_active, group, sort_order)`,
`partners(is_active, sort_order)`, `publications(category, is_featured)`,
`milestones(year, sort_order)`, `units(is_active, sort_order)`.
Pencarian judul memakai generated column + index atas `title->'$.id'` (MySQL 8).

---
## 5. Kebutuhan Konten per Tenant

Skema §4 adalah **irisan bersama** dari kebutuhan yang sudah dikonfirmasi kedua company.
Bagian ini mencatat kebutuhan tiap company dan pemetaannya: masuk skema inti (`tenant/shared/`),
migrasi khusus tenant (`tenant/{slug}/`), atau modul di balik feature flag.

### 5.1 Matriks Modul

| Modul | rekam.org | perikanan.org | Lokasi migrasi | Feature flag |
|---|---|---|---|---|
| Berita + kategori | ✅ | ✅ | `shared/` | `news` |
| Program berita (`related_programs`) | ✅ forest, urban, ocean | ✅ 6 program kelautan | `shared/` (opsi per tenant) | `news_programs` |
| Events | ✅ (+ kategori, biaya, kuota) | ❌ dikonfirmasi tidak perlu | `shared/` | `events` |
| Event rundown | ✅ | ❌ | `shared/` | `event_rundown` |
| Tim | ✅ 5 level | ✅ 3 level | `shared/` | `team` |
| Publikasi | ❌ dikonfirmasi tidak perlu | ✅ | `shared/` | `publications` |
| Partner | ✅ | ✅ **kolom identik** | `shared/` | `partners` |
| Kontak (info + inbox pesan) | ❓ | ❓ | `shared/` | `contacts` |
| **Milestone** | ❌ | ✅ | `tenant/perikanan/` | `milestones` |
| **Unit** | ✅ | ❌ | `tenant/rekam/` | `units` |

Dikonfirmasi 4 September 2026: rekam.org **tidak** memakai modul Publikasi, dan
perikanan.org **tidak** memakai Events maupun rundown. Keduanya tetap berada di
`tenant/shared/` dengan flag mati — inilah gunanya pola "kolom di shared, kapabilitas
per flag": jawaban ini tidak menuntut perubahan skema sama sekali, dan modulnya siap
dinyalakan bila kebutuhannya berubah.

**Temuan penting:** `related_programs` dibutuhkan **kedua** company, hanya beda daftar opsi
(rekam: forest/urban/ocean · perikanan: 6 program kelautan). Karena opsi memang sudah dirancang
tinggal di `site_settings` per tenant, kolomnya **naik ke `tenant/shared/`** — bukan lagi migrasi
khusus perikanan. Satu titik divergensi hilang, dan pola "kolom di shared, opsi per tenant"
jadi pola baku untuk semua taksonomi (level tim, kategori berita/event/publikasi, program).

### 5.2 rekam.org (`tenant: rekam`)

#### a. Berita

| Kebutuhan | Pemetaan | Catatan |
|---|---|---|
| `id`, `timestamps` | inti | |
| `title` | inti `title` (JSON ID/EN) | |
| `body` | inti `body` (JSON ID/EN) | |
| `slug` | inti `slug` (JSON per bahasa) | otomatis dari judul, bisa diedit |
| `related_programs` (multi) | inti (JSON, array slug) | opsi: `forest` (Forest), `urban` (Urban), `ocean` (Ocean) |
| `category` | inti relasi `news_categories` | |
| `image` | inti `cover_path` | |

#### b. Tim

Struktur sama dengan perikanan, hanya beda daftar level. Opsi `level` → kolom inti `group`,
tersimpan di `site_settings` tenant rekam dengan urutan tampil:

`advisor-board` (Advisor Board) → `supervisor-board` (Supervisor Board) →
`chairperson` (Chairperson) → `director` (Director) → `manager` (Manager)

Pemetaan kolom identik §5.3.b (`order`→`sort_order`, `title`→`position`, `profile`→`bio`,
`image`→`photo_path`).

#### c. Events

| Kebutuhan | Pemetaan | Catatan |
|---|---|---|
| `slug` | inti `slug` (JSON per bahasa) | |
| `title` | inti `title` (JSON) | |
| `description` | inti `description` (JSON) | |
| `category` | inti `category` | **baru di inti**; opsi per tenant di `site_settings` |
| `tanggal` | inti `start_at` (+ `end_at` nullable, `is_all_day`) | `end_at` kosong = acara satu hari |
| `lokasi` | inti `location` (JSON) | |
| `biaya` | inti `fee` (decimal, nullable) + `fee_note` (JSON, nullable) | `fee` kosong/0 = gratis; `fee_note` untuk keterangan bebas ("gratis untuk mahasiswa") |
| `kuota` | inti `quota` (int, nullable) | **informatif saja** — lihat catatan |
| `image` | inti `cover_path` | |

**Catatan `kuota`:** CMS ini tidak punya modul pendaftaran, jadi `quota` hanya angka yang
ditampilkan compro. Tidak ada penghitungan sisa kuota / daftar peserta. Bila pendaftaran online
dibutuhkan, itu modul baru dan di luar estimasi saat ini.

#### d. Event Rundown

`event_rundowns` (inti, bagian dari modul events):

| Kolom | Keterangan |
|---|---|
| `id`, `timestamps` | |
| `event_id` | FK ke `events`, `cascadeOnDelete` |
| `time` | jam mulai sesi (`time`) — durasi tersirat dari sesi berikutnya |
| `title` | JSON ID/EN |
| `description` | JSON ID/EN, nullable |
| `sort_order` | **tambahan** — urutan tampil bila ada jam yang sama |

- Diedit sebagai **repeater** di dalam form event (tambah/hapus/drag baris), disimpan sekali
  bersama event lewat satu request — bukan halaman CRUD terpisah.
- Komponen baru `<x-form.repeater>` (Fase 1).
- API publik: rundown disertakan di `GET /events/{slug}`, bukan endpoint terpisah.
- Feature flag `event_rundown` (tab rundown di form hilang bila nonaktif).

#### e. Unit — modul baru, **hanya rekam**

| Kolom | Keterangan |
|---|---|
| `id`, `timestamps` | |
| `name` | nama unit |
| `description` | JSON ID/EN |
| `url` | tautan tujuan |
| `domain` | domain untuk ditampilkan (mis. `perikanan.org`) |
| `logo_path` | dari kebutuhan `logo` |
| `sort_order`, `is_active` | **tambahan** — unit hampir pasti perlu diurutkan & disembunyikan |

- Tabel di `tenant/rekam/*_create_units_table.php`, feature flag `units`,
  endpoint publik `GET /units`.
- **Perlu konfirmasi:** `url` dan `domain` sering identik. Bila `url` selalu `https://{domain}`,
  cukup simpan `domain` saja dan `url` dibentuk otomatis.

#### f. Partner — **dikonfirmasi identik untuk kedua tenant**

| Kebutuhan | Pemetaan |
|---|---|
| `name` | inti `name` |
| `title` | inti `title` (JSON ID/EN) — **baru di inti** |
| `url` | inti `url` |
| `logo` | inti `logo_path` |
| `order` | inti `sort_order` (drag & drop) |

Kolom `category` dan `description` dari draf awal **dihapus** dari skema inti karena tidak ada di
kebutuhan kedua company. Bisa kembali lewat migrasi khusus tenant bila nanti dibutuhkan.
Kolom `is_active` dipertahankan (menyembunyikan partner tanpa menghapus).

Karena kedua company memakai kolom yang sama, modul partner sepenuhnya berada di
`tenant/shared/` tanpa divergensi apa pun.

#### g. Feature flag rekam

```json
{ "news": true, "news_programs": true, "events": true, "event_rundown": true,
  "team": true, "partners": true, "contacts": true, "units": true,
  "publications": false, "milestones": false }
```

`publications: false` sudah **dikonfirmasi**, bukan lagi asumsi: rekam.org tidak memakai
modul Publikasi.

### 5.3 perikanan.org (`tenant: perikanan`)

#### a. Berita

| Kebutuhan | Pemetaan | Catatan |
|---|---|---|
| `id`, `timestamps` | inti | |
| `title` | inti `title` (JSON ID/EN) | |
| `body` | inti `body` (JSON ID/EN) | |
| `related_programs` (multi) | inti (JSON, array slug) | 6 program, lihat di bawah |
| `category` | inti relasi `news_categories` | tabel terpisah (bukan string bebas) agar bisa jadi filter & menu di compro |
| `image` | inti `cover_path` | |

Program: `ocean-accounts` (Ocean Accounts) · `sustainable-fisheries` (Sustainable Fisheries) ·
`marine-conservation` (Marine Conservation) · `species-conservation` (Species Conservation) ·
`blue-carbon` (Blue Carbon) · `ikan` (IKAN)

Berlaku untuk kedua tenant: label & urutan program disimpan di `site_settings` (group `programs`),
kolom `news.related_programs` menyimpan **array slug**. Form memakai
`<x-form.multi-select>`; API publik menyediakan `GET /programs` dan filter
`GET /news?program=blue-carbon` (`JSON_CONTAINS`).

#### b. Tim — seluruhnya tercakup skema inti (hanya beda penamaan)

| Kebutuhan | Kolom inti |
|---|---|
| `order` | `sort_order` (drag & drop) |
| `level` (advisor, manager, officer) | `group` — opsi per tenant di `site_settings` |
| `name` | `name` |
| `title` (jabatan) | `position` (JSON ID/EN) |
| `profile` | `bio` (JSON ID/EN) |
| `image` | `photo_path` |

Halaman index dikelompokkan per **level** sesuai urutan yang ditetapkan di `site_settings`;
drag & drop mengatur urutan **di dalam** satu level. API publik `GET /team` mengembalikan data
yang sudah terkelompok per level.

#### c. Publikasi

| Kebutuhan | Pemetaan |
|---|---|
| `title` | inti `title` (JSON ID/EN) |
| `description` | inti `description` (JSON ID/EN) |
| `category` | inti `category` — opsi per tenant di `site_settings` |
| `file` | inti `file_path` (PDF) |
| `image` | inti `cover_path` |

Metadata ilmiah (`authors`, `journal`, `year`, `doi`, `url`) **tidak dibuat** — tidak ada di
kebutuhan kedua company. Bila rekam.org ternyata membutuhkannya, masuk `tenant/rekam/`
di balik flag `publications_scholarly` (§5.5).

#### d. Milestone — modul baru, **hanya perikanan**

| Kolom | Keterangan |
|---|---|
| `id`, `timestamps` | |
| `title` | JSON ID/EN |
| `body` | JSON ID/EN |
| `cover_path` | dari kebutuhan `image` |
| `year`, `sort_order` | **tambahan** — lihat §5.5 |
| `is_active` | sembunyikan tanpa menghapus |

Tabel di `tenant/perikanan/*_create_milestones_table.php`, feature flag `milestones`:
menu, route dashboard, dan endpoint `GET /milestones` tidak ada sama sekali bila flag mati.

#### e. Partner

Identik dengan rekam.org — `name`, `title`, `url`, `logo`, `order`. Lihat §5.2.f.
Tidak ada perbedaan kolom antar tenant untuk modul ini.

#### f. Feature flag perikanan

```json
{ "news": true, "news_programs": true, "events": false, "event_rundown": false,
  "team": true, "partners": true, "contacts": true, "publications": true,
  "milestones": true, "units": false }
```

### 5.4 Field Bawaan CMS (berlaku kedua tenant)

Di luar daftar yang diberikan, kolom berikut tetap dibuat karena dibutuhkan mesin CMS/compro —
bukan penambahan lingkup, hanya pelengkap:

| Kolom | Alasan |
|---|---|
| `slug` (JSON per bahasa) | URL konten di compro; rekam sudah menyebutnya eksplisit |
| `status` + `published_at` | draft / terjadwal / terbit — tanpa ini tidak ada kontrol penerbitan |
| `excerpt` (JSON) | ringkasan untuk kartu daftar & meta share |
| `meta_title`, `meta_description` (JSON) | SEO per konten |
| `softDeletes` | pemulihan konten terhapus |
| `views` | dipakai widget "berita terpopuler" di dashboard |

Semua kolom teks konten berbentuk **JSON ID/EN** sesuai rencana bilingual — termasuk `body`,
`profile`/`bio`, `description`, dan `title` partner.

### 5.5 Yang Perlu Dikonfirmasi

Pertanyaan 1–3 sudah dijawab sebelum Fase 3 dan tidak mengubah isi `tenant/shared/`.
Sisanya tidak memblokir pekerjaan.

| # | Pertanyaan | Asumsi kerja saat ini |
|---|---|---|
| 1 ✅ | Apakah rekam.org butuh modul **Publikasi**? | **Terjawab: tidak.** Skema tetap di `shared/`, flag rekam mati. |
| 2 ✅ | Bila ya, apakah butuh metadata ilmiah (authors, journal, year, DOI)? | **Gugur** — rekam tidak memakai modul ini. Perikanan cukup title/description/category/file/image. |
| 3 ✅ | Apakah perikanan.org butuh modul **Events** (+ rundown)? | **Terjawab: tidak.** Skema tetap di `shared/`, flag `events` dan `event_rundown` perikanan mati. |
| 4 | Apakah kedua company butuh modul **Kontak** (info kontak + inbox pesan dari form compro)? | Ya, flag hidup di keduanya |
| 5 | **Milestone**: tampil sebagai timeline? | Ya → ada `year` (wajib) + `sort_order`. Bila bukan timeline, `year` dilepas |
| 6 | **Unit**: apakah `url` selalu `https://{domain}`? | Disimpan dua kolom terpisah; bila selalu sama, cukup `domain` |
| 7 | **Event `biaya`**: angka rupiah, atau teks bebas ("Rp250.000 / gratis untuk mahasiswa")? | `fee` (angka, nullable) + `fee_note` (teks bebas, nullable) |
| 8 | **Event `kuota`**: perlu pendaftaran online + hitung sisa kuota? | Tidak — `quota` hanya angka informatif |

Sudah terjawab: **Partner** memakai kolom identik di kedua company (`name`, `title`, `url`,
`logo`, `order`) — tidak perlu kategori/pengelompokan, seluruhnya di `tenant/shared/`.

---

## 6. Roadmap & Estimasi

Asumsi: 1 developer, 1 hari = 8 jam efektif.

### Fase 0 — Setup Fondasi · 1 hari
- `composer create-project laravel/laravel:^10`, Breeze (Blade), spatie/permission, activitylog.
- Tailwind + Alpine + Chart.js via Vite; `tailwind.config.js` dengan palet teal/amber/red.
- `config/database.php`: koneksi `mysql` (pusat) + `tenant` (placeholder dinamis).
- Buat 3 database kosong: `cms_central`, `cms_rekam`, `cms_perikanan`.
- `.env.example` (`DB_DATABASE=cms_central`, `DB_TENANT_PREFIX=cms_`), README singkat,
  `context.md` masuk repo.
- **Selesai bila:** `npm run dev` + `php artisan serve` jalan, halaman kosong ber-Tailwind tampil.

### Fase 1 — Prototype Layout + Style (PRIORITAS PERTAMA) · 5 hari
Statis, data dummy (array/JSON), **belum menyentuh database**. Tujuan: mengunci desain & komponen.

| Hari | Keluaran |
|---|---|
| 1 | Design token Tailwind (teal/amber/red, radius, shadow, font). `<x-icon>` berisi set Heroicons yang dipakai. Komponen atom: button, badge, card, alert, avatar, spinner, breadcrumb, page-header, empty-state, stat-card. |
| 2 | `layouts/app.blade.php`: **sidebar expandable** (grup menu buka/tutup, collapse ke mode ikon, state disimpan di `localStorage`, drawer di mobile), topbar (tenant switcher, notifikasi, user menu), area flash message. Halaman login. |
| 3 | Komponen form lengkap (field, input, textarea, select, **multi-select**, **repeater**, toggle, checkbox, date, tag-input, image-upload, file-upload, lang-tabs ID/EN, editor) + komponen tabel (toolbar filter, th sortable, skeleton, state error/empty, pagination, row-actions) + modal, drawer, dropdown, tabs. |
| 4 | Halaman prototype (bagian 1): Dashboard (4 stat-card + 2 Chart.js + tabel aktivitas), Berita index & form (multi-select program), Events index & form (**tab rundown dengan repeater**, biaya, kuota), Tim index (dikelompokkan per level + drag & drop) & form. |
| 5 | Halaman prototype (bagian 2): Publikasi, **Milestone**, **Unit**, Partner grid, Kontak inbox + detail, Pengaturan (termasuk editor taksonomi level/kategori/program), Tenant & feature flag, Users & Roles. Lalu sapuan responsif + aksesibilitas seluruh halaman. |

Semua tabel/daftar sudah memakai pola Alpine `fetch()` ke **JSON dummy** dengan state
loading / error / empty / ready sejak fase ini — bukan data yang ditanam di Blade.

- **Selesai bila:** semua halaman bisa diklik dan responsif (mobile/tablet/desktop), tidak ada CSS
  custom di luar Tailwind, tidak ada `<script>` inline selain `x-data`, tidak ada duplikasi markup
  yang seharusnya menjadi komponen.

### Fase 2 — Tenancy, Auth, RBAC · 3,5 hari
- Migrasi pusat + model `Tenant`, `User`; `TenantManager`, middleware `ResolveTenant`, `TenantModel`.
- Command `tenants:migrate` / `tenants:seed`: loop semua tenant, jalankan `tenant/shared/` lalu
  `tenant/{slug}/`; mendukung `--tenant=slug`, `--fresh`, dan `tenants:status` (cek migrasi tertinggal).
- Feature flag: kolom `tenants.features`, `Tenant::hasFeature()`, middleware `feature:{nama}`,
  helper Blade `@feature`, dan UI toggle modul per tenant (halaman kelola tenant, `super-admin`).
- Seeder tenant awal: `rekam` (Rekam · `cms_rekam` · `rekam.org`) dan
  `perikanan` (Perikanan · `cms_perikanan` · `perikanan.org`) + API key masing-masing.
- Tenant switcher fungsional; role `super-admin`, `admin`, `editor`, `viewer` + permission per modul
  (`news.view/create/update/delete/publish`, dst.), policy, dan gate di sidebar (`@can` + `@feature`).
- CRUD user & role, aktivasi/deaktivasi, reset password.
- **Selesai bila:** dua DB tenant termigrasi (termasuk satu migrasi khusus tenant sebagai bukti
  mekanismenya jalan), ganti tenant mengubah sumber data, menu ikut permission **dan** feature flag.

### Fase 3 — Modul Berita & Events · 5 hari
- Migrasi/model/factory/seeder tenant, Form Request, Policy, API Resource.
- `TaxonomyService` + seeder `site_settings`: level tim, kategori, dan **daftar program per tenant**
  (rekam: forest/urban/ocean · perikanan: 6 program kelautan).
- Internal API CRUD + filter (status, kategori, program, tanggal, pencarian), sort, paginate.
- Berita: upload cover, editor, tab ID/EN, slug otomatis per bahasa, **multi-select program**,
  jadwal publikasi, aksi massal (publish/arsip/hapus), soft delete + restore.
- Events: kategori, tanggal, lokasi, biaya + keterangan biaya, kuota, cover, dan
  **rundown sebagai repeater** yang tersimpan satu request bersama event (flag `event_rundown`).
- **Selesai bila:** CRUD dua modul lolos feature test, rundown tersimpan/terurut benar,
  error validasi (termasuk error per baris rundown) tampil inline tanpa reload.

### Fase 4 — Tim, Publikasi, Partner, Kontak · 4 hari
- Tim: CRUD + reorder drag & drop (`PATCH /dash-api/v1/team/reorder`, batch `sort_order`),
  dikelompokkan per **level** (advisor/manager/officer) dengan reorder di dalam level;
  opsi level dari `site_settings`.
- Publikasi: CRUD + kategori (opsi per tenant), upload PDF + cover, filter kategori, featured.
- Partner: CRUD + `title` + reorder + grid preview logo.
- Kontak: inbox pesan (baca/arsip/hapus, detail, balas via mailto) + form info kontak di `site_settings`.
- **Selesai bila:** urutan tetap tersimpan setelah reload, keempat modul lolos feature test.

### Fase 4b — Modul Khusus per Tenant · 2 hari
- **Milestone** (perikanan): migrasi `tenant/perikanan/`, model, policy, Form Request,
  CRUD internal API, halaman index (urut `year`, `sort_order`) + form, endpoint publik
  `GET /milestones`, flag `milestones`.
- **Unit** (rekam): migrasi `tenant/rekam/`, model, policy, Form Request, CRUD internal API,
  halaman index (reorder drag & drop) + form dengan upload logo, endpoint publik `GET /units`,
  flag `units`.
- **Selesai bila:** modul milestone tidak muncul sama sekali saat tenant `rekam` aktif dan modul
  unit tidak muncul saat tenant `perikanan` aktif — menu hilang, route 404, endpoint publik tidak
  terdaftar, dan tabelnya memang tidak ada di DB tenant tersebut. Dibuktikan feature test
  untuk kedua arah.

### Fase 5 — Media Library & Pengaturan Situs · 2 hari
- `MediaService` (upload, validasi mime/ukuran, thumbnail, path per tenant), model `Media`.
- Modal media picker reusable (dipakai cover berita/event, foto tim, logo partner, logo unit,
  gambar milestone).
- Halaman pengaturan: identitas, sosial media, SEO default, embed peta, generate/rotate API key,
  dan **editor taksonomi** (level tim, kategori berita/event/publikasi, daftar program).
- **Selesai bila:** satu komponen picker dipakai semua modul, file tersimpan terpisah per tenant.

### Fase 6 — API Publik untuk Compro · 3 hari
- `ResolvePublicTenant` (`X-Api-Key` → tenant, atau slug di path), `SetPublicLocale` (`?lang`).
- Endpoint read-only: `news`, `news/{slug}`, `news-categories`, `programs`, `events`,
  `events/{slug}` (menyertakan rundown), `team` (terkelompok per level), `publications`,
  `partners`, `milestones`, `units`, `settings`; plus `POST contact` (throttle + honeypot).
  Endpoint `programs`, `milestones`, dan `units` hanya terdaftar untuk tenant yang flag-nya aktif.
- Filter: `news?program=`, `news?category=`, `events?upcoming=1`, `publications?category=`.
- Pagination, filter, `?fields`, response cache (tag per tenant, invalidasi saat konten disimpan),
  ETag/`Cache-Control`, rate limit, CORS dibatasi ke `rekam.org` dan `perikanan.org`
  (+ subdomain `www`) — bukan `*`.
- Dokumentasi endpoint di `docs/api-public.md` + koleksi contoh request.
- **Selesai bila:** semua endpoint lolos test, respons `?lang=en` benar dengan fallback ke `id`.

### Fase 7 — Dashboard Analitik & Audit · 2 hari
- `StatsService` + `StatsApiController`: jumlah konten per status, tren publikasi 12 bulan,
  event mendatang, pesan belum dibaca, berita terpopuler.
- Chart.js (line + doughnut/bar) via `<x-chart>`, data dari internal API, dengan loading state.
- Activity log per tenant + halaman riwayat aktivitas.
- **Selesai bila:** chart terisi data nyata dan ikut berubah saat tenant di-switch.

### Fase 8 — QA, Hardening, Deploy · 3 hari
- Feature test tiap modul + test isolasi tenant; Pest/PHPUnit; Pint.
- Hardening: rate limit login, konfirmasi dua langkah untuk hapus, validasi upload ketat,
  security header, audit kebocoran data antar tenant, backup DB.
- `php artisan optimize`, `npm run build`, konfigurasi Nginx + queue worker + scheduler
  (publikasi terjadwal), panduan deploy & prosedur menambah tenant baru.
- **Selesai bila:** checklist rilis di `context.md` §9 lolos semua.

### Rekap

| Fase | Hari |
|---|---|
| 0 · Setup | 1 |
| 1 · Prototype layout + style | 5 |
| 2 · Tenancy, Auth, RBAC, feature flag | 3,5 |
| 3 · Berita & Events (+ rundown, program) | 5 |
| 4 · Tim, Publikasi, Partner, Kontak | 4 |
| 4b · Modul khusus per tenant (Milestone, Unit) | 2 |
| 5 · Media & Pengaturan | 2 |
| 6 · API Publik | 3 |
| 7 · Dashboard & Audit | 2 |
| 8 · QA & Deploy | 3 |
| **Total** | **30,5 hari kerja (± 6–7 minggu)** |

Buffer risiko +20% → **~37 hari**. Jalur kritis: Fase 2 (tenancy) — semua modul bergantung padanya.

Angka ini mencakup seluruh kebutuhan yang sudah dikonfirmasi kedua company (§5). Yang **belum**
dihitung: modul pendaftaran event (kuota terpakai, daftar peserta) dan metadata ilmiah publikasi
bila ternyata dibutuhkan — masing-masing ± 2–3 hari.

---

## 7. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Query konten jalan tanpa tenant aktif | Data dua company tercampur | `TenantModel` + exception bila tenant belum resolve + test isolasi |
| Migrasi tenant lupa dijalankan di satu DB | Error di produksi | `tenants:migrate` loop semua tenant + `tenants:status` + indikator di dashboard |
| Skema dua company makin melenceng (drift) | Kode bersama rusak di satu tenant | Skema inti hanya di `tenant/shared/`; perbedaan wajib lewat `tenant/{slug}/` + feature flag; kode bersama dilarang menyentuh kolom khusus tenant |
| Kolom JSON menyulitkan pencarian | Fitur search lambat | MySQL 8 generated column + index untuk `title->id`, `slug->id` |
| Komponen tumbuh tanpa standar | Duplikasi markup | Fase 1 mengunci komponen sebelum modul dibangun; review terhadap `context.md` §3 |
| Cache API publik stale | Compro menampilkan data lama | Cache tag per tenant, invalidasi di observer model |
| Konten EN belum lengkap | Compro versi EN kosong | Fallback ke `id` + indikator kelengkapan terjemahan di halaman index |
| Modul ❓ di §5.1 (publikasi rekam, events perikanan) salah asumsi | Isi `tenant/shared/` salah → migrasi ulang | Modul tetap dibuat di `shared/` + flag; mematikan flag murah, menambah modul mahal. Konfirmasi §5.5 sebelum Fase 3 |
| Taksonomi (level/kategori/program) ditulis sebagai enum di kode | Setiap perubahan daftar butuh deploy | Opsi wajib di `site_settings` + `TaxonomyService`; dijaga aturan keras `context.md` §5.12 |

---

## 8. Di Luar Lingkup (fase ini)

Website compro itu sendiri, page builder visual, workflow approval berjenjang, versioning konten,
multi-bahasa untuk UI CMS, notifikasi email berlangganan, dan tenant ke-3 ke atas (arsitektur sudah
mendukung, tetapi belum di-provision).
