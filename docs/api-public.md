# API Publik — cms.rekam.org

API baca-saja untuk website company profile (compro). Tidak ada sesi/login — setiap
permintaan diautentikasi lewat header `X-Api-Key`, yang sekaligus menentukan company
(tenant) mana yang datanya dibaca.

## Autentikasi

```
X-Api-Key: rekam_AbCdEf1234...
```

- Key didapat dari **Pengaturan Situs › API Key** di dashboard CMS (super admin saja),
  ditampilkan **hanya sekali** saat pertama dibuat atau diputar (rotate).
- Key tidak valid, tidak dikirim, atau milik company yang sedang nonaktif → `401`:
  ```json
  { "message": "API key tidak valid atau tidak ditemukan." }
  ```
- Satu key hanya berlaku untuk satu company — tidak ada cara membaca data company lain
  lewat key ini.

## Bahasa (`?lang=`)

Semua field yang bisa diterjemahkan (judul, isi, deskripsi, dst.) dikembalikan sebagai
string tunggal sesuai `?lang=id` (default) atau `?lang=en`. Bila terjemahan Inggris
kosong, otomatis jatuh ke bahasa Indonesia — field tidak pernah kosong begitu saja
selama salah satu bahasa terisi.

```
GET /api/v1/news?lang=en
```

## Amplop respons

**Daftar** (list):
```json
{
  "data": [ ... ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

**Satu record / data gabungan** (detail, `team`, `programs`, `settings`, dst.):
```json
{ "data": { ... } }
```

## Query parameter yang berlaku umum

| Parameter | Keterangan |
|---|---|
| `lang` | `id` (default) atau `en`. |
| `fields` | Daftar field dipisah koma, mis. `?fields=id,title` — hanya field itu yang dikembalikan per item. |
| `page`, `per_page` | Hanya untuk endpoint daftar. `per_page` dibatasi oleh `cms.max_per_page`. |

## Cache

Respons di-cache per company selama beberapa menit (`Cache-Control`, `ETag` — kirim
`If-None-Match` untuk mendapat `304` bila belum berubah). Permintaan tanpa filter/halaman
(kondisi paling umum) langsung diperbarui begitu konten disimpan di dashboard; kombinasi
filter/halaman lain mengikuti masa berlaku cache tersebut.

## Endpoint

Setiap endpoint di bawah **tidak terdaftar sama sekali** (404, bukan daftar kosong) bila
modulnya nonaktif untuk company tersebut.

### Berita

```
GET /api/v1/news
GET /api/v1/news?program=<slug>
GET /api/v1/news?category=<slug-kategori>
GET /api/v1/news/{slug}
GET /api/v1/news-categories
```
`{slug}` boleh slug bahasa Indonesia maupun Inggris. Hanya artikel berstatus terbit
(dan sudah melewati jadwal terbitnya) yang muncul.

### Program (khusus tenant dengan modul `news_programs`)

```
GET /api/v1/programs
```
Daftar `{value, label}` — dipakai untuk memfilter `news?program=`.

### Events

```
GET /api/v1/events
GET /api/v1/events?upcoming=1
GET /api/v1/events?category=<slug>
GET /api/v1/events/{slug}
```
Detail event menyertakan `rundowns` bila modul rundown aktif untuk company tersebut;
bila tidak, field `rundowns` tidak ada sama sekali di respons.

### Tim

```
GET /api/v1/team
```
Dikelompokkan per level sesuai urutan yang diatur di Pengaturan › Taksonomi:
```json
{
  "data": [
    { "level": { "value": "director", "label": "Direktur" }, "members": [ ... ] },
    { "level": { "value": "manager", "label": "Manajer" }, "members": [ ... ] }
  ]
}
```

### Publikasi (khusus tenant dengan modul `publications`)

```
GET /api/v1/publications
GET /api/v1/publications?category=<slug>
```

### Partner

```
GET /api/v1/partners
```

### Milestone (khusus tenant dengan modul `milestones`)

```
GET /api/v1/milestones
```

### Unit (khusus tenant dengan modul `units`)

```
GET /api/v1/units
```

### Pengaturan Situs

```
GET /api/v1/settings
```
Gabungan identitas situs, sosial media, SEO bawaan, sematan peta, dan informasi kontak —
sekali panggil untuk header/footer/halaman kontak compro.

### Kontak (`POST`, satu-satunya endpoint tulis)

```
POST /api/v1/contact
Content-Type: application/json

{
  "name": "Nama Pengirim",
  "email": "pengirim@example.com",
  "phone": "081234567890",
  "subject": "Subjek Pesan",
  "message": "Isi pesan.",
  "website": ""
}
```
- `website` adalah honeypot — sembunyikan lewat CSS di form, jangan pernah diisi
  pengunjung asli. Jika terisi, permintaan tetap dibalas `201` (agar bot tidak tahu
  ditangkap) tapi pesan **tidak** benar-benar disimpan.
- Dibatasi laju permintaan lebih ketat dari endpoint baca lainnya — permintaan berlebih
  dibalas `429`.
- `status` dan `ip` selalu ditentukan server, tidak bisa dikirim dari form.

## CORS

Hanya domain compro resmi tiap company (mis. `rekam.org`, `www.rekam.org`) yang
diizinkan memanggil API ini langsung dari browser. Pemanggilan dari server (server-side
rendering, cron, dsb.) tidak terkena batasan CORS.
