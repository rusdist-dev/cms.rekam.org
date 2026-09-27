# API Datasource Eksternal — `/api/v1/ext/*`

Panduan menambahkan API yang membaca **database milik sistem lain** — bukan database
pusat (`mysql`) dan bukan database konten tenant (`tenant`).

Aplikasi ini sekarang mengenal tiga jenis database:

| Jenis | Koneksi | Isi | Diatur di |
|---|---|---|---|
| Pusat | `mysql` | user, role, registry tenant, activity log, session | `.env` (`DB_*`) |
| Konten tenant | `tenant` | konten per company; nama DB ditentukan saat runtime | tabel `tenants` |
| **Eksternal** | `ds_<key>` | database sistem lain (COAST, dsb.) | `config/datasources.php` + `.env` |

Bedanya dengan koneksi tambahan biasa di `config/database.php`: satu registry menjawab
pertanyaan "database apa saja yang disentuh aplikasi ini, dan mana yang boleh ditulis" —
dan flag read-only di situ **ditegakkan**, bukan sekadar niat.

---

## Menambahkan datasource baru

### 1. Kredensial di `.env`

```dotenv
DS_COAST_HOST=10.0.0.21
DS_COAST_PORT=3306
DS_COAST_DATABASE=coast_prod
DS_COAST_USERNAME=cms_reader
DS_COAST_PASSWORD=rahasia
```

Contoh di seluruh dokumen ini memakai `coast`, satu-satunya datasource yang sudah
hidup. Untuk datasource kedua, ganti `coast` dengan key baru Anda di setiap langkah.

Gunakan user database yang **hanya punya GRANT SELECT**. Aplikasi juga menolak tulis,
tapi grant adalah lapisan yang tetap bertahan kalau kode kita yang salah.

Datasource yang `_DATABASE`-nya kosong dianggap **tidak ada di lingkungan ini**: endpoint-nya
menjawab `503` alih-alih menyambung ke database default. Ini disengaja — baris `.env` yang
terlupa harus gagal dengan menyebut nama, bukan diam-diam membaca database yang salah.

### 2. Daftarkan di `config/datasources.php`

```php
'sources' => [

    'coast' => [
        'label' => 'COAST',
        'read_only' => true,
        'connection' => [
            'host' => env('DS_COAST_HOST', '127.0.0.1'),
            'port' => env('DS_COAST_PORT', '3306'),
            'database' => env('DS_COAST_DATABASE'),
            'username' => env('DS_COAST_USERNAME'),
            'password' => env('DS_COAST_PASSWORD'),
        ],
    ],

],
```

Blok `connection` di-merge di atas `defaults` (MySQL, utf8mb4, `strict` mati). Datasource
yang bukan MySQL cukup menimpa `driver`/`port`-nya sendiri.

`read_only` **default-nya `true`** kalau tidak ditulis — sebuah sumber yang ditambahkan
tanpa flag diperlakukan sebagai data milik orang lain sampai pemiliknya bilang lain.

### 3. Pastikan tersambung

```bash
php artisan cms:datasources          # daftar + status konfigurasi
php artisan cms:datasources --ping   # benar-benar buka koneksi
```

`--ping` keluar dengan kode non-nol kalau ada datasource yang terdaftar tapi gagal
disambungkan — itu yang pantas dijadikan gerbang di CI/deploy.

### 4. Model (opsional)

Untuk skema yang layak dimodelkan, turunkan dari `ExternalModel` dan sebutkan **key
datasource-nya**, bukan nama koneksinya:

```php
namespace App\Models\External\Coast;

use App\Models\Concerns\ExternalModel;

class Ekosistem extends ExternalModel
{
    protected string $datasource = 'coast';

    protected $table = 'coast_ecosystem';
}
```

`ExternalModel` mematikan `$timestamps` secara default (skema asing jarang punya pasangan
`created_at`/`updated_at`) dan menolak `save()`/`delete()` pada datasource read-only dengan
pesan yang menyebut nama modelnya.

Skema legacy yang tidak layak dimodelkan cukup pakai query builder lewat registry —
jangan pernah menulis `DB::connection('ds_coast')` langsung, karena nama yang salah ketik
tetap lolos kompilasi:

```php
app(DatasourceRegistry::class)->connection('coast')->table('coast_ecosystem')->get();
```

### 5. Controller + route

```php
namespace App\Http\Controllers\ExtApi\Coast;

use App\Http\Controllers\ExtApi\ExternalApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesaController extends ExternalApiController
{
    protected string $datasource = 'coast';

    public function index(Request $request): JsonResponse
    {
        return $this->dataResponse($request, 'desa', fn () => app(CoastRegionService::class)->list());
    }
}
```

Lalu di `routes/ext-api.php`, di dalam grup yang sudah ada:

```php
Route::middleware(['datasource:coast', $cacheHeaders])
    ->prefix('coast')
    ->name('coast.')
    ->group(function () {
        Route::get('desa', [DesaController::class, 'index'])->name('desa.index');
    });
```

Hasilnya: `GET /api/v1/ext/coast/desa`.

---

## Kontrak API

### Autentikasi

Sama persis dengan API publik: header `X-Api-Key` (lihat [api-public.md](api-public.md)).
Situs compro tidak perlu kredensial kedua. Yang berbeda hanya sumber datanya.

### Amplop respons

Identik dengan `/api/v1/*`, jadi klien yang sudah bicara dengan API publik tidak butuh
kode baru:

```json
{ "data": [ ... ], "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 } }
```

`?per_page=`, `?fields=a,b,c`, dan `{ "data": ... }` untuk non-list berlaku sama.

### Kode status khusus

| Kode | Arti |
|---|---|
| `401` | `X-Api-Key` tidak valid |
| `429` | melewati `DS_RATE_LIMIT` (default 120/menit per IP) |
| `404` | data yang diminta tidak ada (mis. desa tanpa pendataan terverifikasi) |
| `503` | datasource tidak dikonfigurasi di lingkungan ini, atau tidak terdaftar |

### Cache & throttle

Punya sendiri, terpisah dari API publik (`DS_CACHE_TTL`, `DS_RATE_LIMIT`):

- **Key cache-nya per datasource**, bukan per tenant — data eksternal itu baris yang sama
  siapa pun yang meminta. Meng-key-nya per tenant hanya akan melipatgandakan payload identik
  dan beban ke sistem yang justru paling lambat di rantai ini.
- **Throttle-nya sendiri**, dan `routes/ext-api.php` terpisah dari `routes/api.php`, supaya
  sistem pihak ketiga yang lambat atau mati tidak bisa menyeret API konten tenant bersamanya.

---

## Endpoint COAST

Dua endpoint yang menyuplai peta publik. Keduanya **hanya** membaca form berstatus
`verified` — draft, submitted, rejected, dan yang sudah dihapus tidak pernah keluar,
aturan yang sama dengan `published` pada konten CMS.

### `GET /api/v1/ext/coast/desa`

Daftar desa yang punya pendataan terverifikasi, beserta titik markernya.
Diurutkan **berdasarkan nama desa A–Z**, dan **tidak dipaginasi**: peta butuh semua
marker sekaligus.

```json
{
  "data": [
    {
      "desa_kode": "33.21.12.2011",
      "desa": "Purworejo",
      "kecamatan_kode": "33.21.12",
      "kecamatan": "Bonang",
      "kabupaten_kode": "33.21",
      "kabupaten_kota": "Kabupaten Demak",
      "provinsi_kode": "33",
      "provinsi": "Jawa Tengah",
      "koordinat": { "lat": -6.8201893594804, "lng": 110.56238796756 },
      "jumlah_form": 5,
      "pendataan_terakhir": "2026-07-19"
    }
  ]
}
```

`koordinat` bisa `null` bila desa itu belum punya baris di `wilayah_boundaries` —
sengaja null, bukan `0,0`, karena marker di lepas pantai Afrika terlihat seperti data.

### `GET /api/v1/ext/coast/desa/{desa_kode}`

Summary satu desa. `404` bila desa itu tidak punya form terverifikasi.

```json
{
  "data": {
    "wilayah": { "desa_kode": "...", "desa": "...", "kecamatan": "...", "kabupaten_kota": "...", "provinsi": "..." },
    "peta": { "lat": -6.82, "lng": 110.56, "luas": null, "penduduk": null, "path": [[[-6.82, 110.56], ...]] },
    "pendataan": { "jumlah_form": 5, "terakhir": "2026-07-19" },
    "statistik": {
      "tahun_baru": 2026,
      "tahun_lama": 2025,
      "metrik": [
        { "key": "luas_area_konservasi", "label": "Luas Area Konservasi", "unit": "ha", "decimals": 2, "baru": 0, "lama": 0 },
        {
          "key": "dampak_ekonomi_produksi", "label": "Dampak Ekonomi (Produksi)", "unit": "kg", "decimals": 2,
          "baru": 6030, "lama": 1777,
          "children_sum_to_total": true,
          "children": [
            {
              "key": "silvofishery", "label": "Silvofishery", "unit": "kg", "decimals": 2,
              "baru": 3760, "lama": 1777,
              "children_sum_to_total": true,
              "children": [
                { "key": "bandeng", "label": "Bandeng", "unit": "kg", "decimals": 2, "baru": 1850, "lama": 0 },
                { "key": "udang-windu", "label": "Udang Windu", "unit": "kg", "decimals": 2, "baru": 1400, "lama": 0 },
                { "key": "kepiting", "label": "Kepiting", "unit": "kg", "decimals": 2, "baru": 510, "lama": 1777 }
              ]
            }
          ]
        }
      ]
    },
    "gambar": [{ "url": "...", "keterangan": "..." }],
    "rehabilitasi": [{ "tanggal": "2026-07-31", "ekosistem": "Mangrove", "status_lahan": "Pribadi", "luas_area_direhabilitasi": 1.5, "pelaksana": "...", "kolaborator": "...", "jumlah_bibit": 6000, "survival_rate": 0 }],
    "pelatihan": [{ "tanggal": "...", "nama": "...", "peserta": 30, "peserta_pria": 18, "peserta_wanita": 12, "peserta_remaja": 5, "peserta_lansia": 3, "peserta_disabilitas": 2 }]
  }
}
```

**`peta.path`** adalah array cincin poligon berisi titik **`[lat, lng]`** — urutannya
kebalikan GeoJSON (`[lng, lat]`), dan memang begitu yang dipakai Leaflet.

### Struktur `statistik.metrik`

**Sembilan metrik teratas dengan urutan tetap**, masing-masing membawa `baru` (tahun
berjalan) dan `lama` (tahun sebelumnya). Empat di antaranya punya `children`:

| `key` | `unit` | `children` | `children_sum_to_total` |
|---|---|---|---|
| `luas_ekosistem_total` | ha | `luas_ekosistem_mangrove`, `_lamun`, `_terumbu_karang` | `false` |
| `nilai_ekonomi_total` | Rp | `nilai_valuasi`, `nilai_pendapatan` | `true` |
| `luas_area_konservasi` | ha | — | — |
| `luas_area_direhabilitasi` | ha | — | — |
| `dampak_ekonomi_produksi` | kg | jenis kegiatan → komoditas | `true` |
| `dampak_ekonomi_unit_terjual` | unit | jenis kegiatan → komoditas | `true` |
| `orang_dilatih_total` | orang | `_pria` `_wanita` `_remaja` `_lansia` `_disabilitas` | `false` |
| `orang_terlibat_total` | orang | kelima rincian yang sama | `false` |
| `nilai_stok_karbon` | Mg C | — | — |

`nilai_stok_karbon` bersatuan **Mg C** (megagram karbon; 1 Mg C = 1 ton karbon). Nama
kolom di hulu hanya "nilai" dan tidak menyatakan satuan apa pun, jadi angka ini
**bukan** rupiah meski namanya terbaca begitu.

Setiap node punya bentuk yang sama (`key`, `label`, `unit`, `decimals`, `baru`, `lama`),
jadi satu komponen rekursif bisa merender seluruh pohonnya. Node tanpa rincian **tidak
punya** key `children` sama sekali.

#### `children_sum_to_total` — baca ini sebelum menjumlahkan

Flag ini menjawab satu pertanyaan: boleh tidak rincian ini diperlakukan sebagai
penguraian utuh dari induknya — dijumlahkan, dijadikan pie chart, atau dihitung sisanya
sebagai "lainnya"?

- **`true`** — boleh. `nilai_ekonomi_total` memang didefinisikan sebagai jumlah kedua
  anaknya, dan pada dampak ekonomi setiap jenis kegiatan hanya masuk ke satu induk
  (satuan yang dipakainya justru yang menentukan induknya) serta setiap baris hanya
  membawa satu komoditas.
- **`false`** — **jangan**. Induknya adalah angka yang dilaporkan, anaknya hanya bagian
  yang bisa diatribusikan:
  - `luas_ekosistem_total` — baris "Mangrove,Lamun dan Terumbu Karang" membawa satu
    angka luas yang tidak bisa dibagi tiga, jadi ia masuk ke induk dan tidak ke satu pun
    anak. Menjumlahkan anak akan **melaporkan lebih kecil** dari yang sebenarnya.
  - `orang_dilatih_total` / `orang_terlibat_total` — kategorinya **saling tumpang
    tindih** (perempuan berusia remaja terhitung di `wanita` sekaligus `remaja`), dan
    hulu membolehkan total diisi tanpa rinciannya. Menjumlahkannya bisa lebih besar
    **atau** lebih kecil dari induk.

Rincian di setiap level terurut dari kontributor terbesar tahun berjalan, dengan nama
sebagai tie-break. Komoditas yang kosong di hulu tampil sebagai `Lainnya`.

**`riwayat` bersifat sepanjang waktu**, bukan dua tahun: `rehabilitasi` dan `pelatihan`
mengembalikan seluruh catatan (maksimal 100, terbaru dulu). Hanya `statistik` yang
dibatasi dua tahun.

### `GET /api/v1/ext/coast/statistik`

Total seluruh desa — panel headline, tanpa `{desa_kode}` yang membatasinya. Aturannya
sama dengan summary desa: hanya form `verified`, tahun berjalan melawan tahun sebelumnya.

```json
{
  "data": {
    "pendataan": { "jumlah_form": 81, "jumlah_desa": 19, "terakhir": "2026-07-25" },
    "statistik": {
      "tahun_baru": 2026,
      "tahun_lama": 2025,
      "metrik": [
        { "key": "luas_ekosistem_mangrove", "label": "Luas Ekosistem Mangrove", "unit": "ha", "decimals": 2, "baru": 6931.84, "lama": 6913.53 }
      ]
    }
  }
}
```

**Dua belas metrik, datar, urutan tetap** — tidak ada `children` di level ini:

`luas_ekosistem_mangrove`, `luas_ekosistem_lamun`, `luas_ekosistem_terumbu_karang`,
`nilai_valuasi`, `nilai_pendapatan`, `luas_area_konservasi`,
`luas_area_direhabilitasi`, `dampak_ekonomi_produksi`, `dampak_ekonomi_unit_terjual`,
`orang_dilatih_total`, `orang_terlibat_total`, `nilai_stok_karbon`.

Dua angka yang diturunkan oleh summary desa — `luas_ekosistem_total` dan
`nilai_ekonomi_total` — **sengaja tidak ada di sini**. Keduanya penjumlahan yang
bagian-bagiannya berarti berbeda pada skala ini: satu baris ekosistem gabungan yang
tidak bisa diatribusikan ke jenis mana pun, dan nilai valuasi yang tiga kali lipat orde
besarnya dibanding pendapatan di sebelahnya. Satu angka headline justru menyembunyikan
persis hal itu.

`pendataan` bersifat **sepanjang waktu**, bukan dua tahun — sama seperti `pendataan`
pada summary desa: berapa banyak pendataan yang ada dan kapan yang terbaru dikumpulkan.

**Total nasional tidak dijamin sama dengan jumlah ke-19 desa.** Saat ini memang sama
persis untuk setiap metrik, tapi itu kebetulan data: dua form terverifikasi tidak punya
`desa_kode`, sehingga ikut ke total nasional tetapi tidak masuk ke desa mana pun.
Begitu salah satunya punya baris ekosistem atau ekonomi, selisihnya muncul.

---

### `GET /api/v1/ext/coast/kawasan-konservasi`

Daftar kawasan konservasi perairan. **Tabel referensi, bukan data pendataan** — tidak
ada penyaringan `verified` di sini, dan kawasan yang belum dirujuk catatan blue carbon
mana pun tetap tampil. Daftar acuan yang menyembunyikan entri justru tidak berguna bagi
yang sedang mencocokkan data.

**Tidak dipaginasi**, seperti daftar desa — 11 baris yang dipakai pemanggil untuk
mencocokkan datanya sendiri, jadi dikirim utuh. Tidak ada `meta` pada respons ini.

```json
{
  "data": [
    {
      "id": 3,
      "nama_kawasan": "Kawasan Konservasi Segara Anakan",
      "id_mpa": "T393",
      "luas_area_dikonservasi": 1730.58,
      "pelaksana_konservasi": "Dinas Kelautan, Perikanan dan Pengelola Sumber Daya Kawasan Segara Anakan (DKP2SKSA) Kabupaten Cilacap"
    }
  ]
}
```

- `id_mpa` adalah identitas kawasan di register MPA nasional (`T244`, `T946`, …) — itu
  yang dipakai untuk merujuk kawasan ini di luar COAST. Bisa `null`.
- `pelaksana_konservasi` bisa `null`.
- Terurut berdasarkan nama, **case-insensitive secara eksplisit** (`lower()`): satu
  kawasan di hulu tersimpan dengan huruf kapital semua, dan urutannya tidak boleh
  bergantung pada collation database asing.
- `created_at`/`updated_at` tidak dikeluarkan: itu mencatat kapan sistem hulu terakhir
  menyentuh barisnya sendiri, bukan apa pun tentang kawasannya.

---

### Dua hal yang perlu diketahui tentang angkanya

**Nama wilayah datang dari tabel `wilayah`, bukan dari isian form.** Kolom nama pada
`identitas_coast` adalah teks bebas dan saling bertentangan: satu `desa_kode` muncul
sebagai "Teluk Awur" sekaligus "Telukawur", dan satu form mencatat Purworejo di
kecamatan "Purworejo" alih-alih "Bonang". Kode-nya konsisten, jadi kode dipakai
sebagai identitas dan `wilayah` sebagai sumber nama (`CoastWilayahResolver`).

**`dampak_ekonomi_produksi` dan `dampak_ekonomi_unit_terjual` dipisah per jenis
kegiatan, bukan per kolom.** Kolom `produksi` berarti kilogram untuk Perikanan
Tangkap, Budidaya, dan Silvofishery, tetapi **pack** untuk Pengolahan Hasil Perikanan.
Penjualan Bibit Mangrove memakai `jumlah_bibit_terjual` dan Wisata memakai
`jumlah_kunjungan`. Pemetaannya ada di `Ekonomi::OUTPUT_COLUMNS` — **jenis kegiatan
baru yang satuannya bukan kilogram harus ditambahkan ke sana**, kalau tidak ia akan
ikut terhitung sebagai kilogram, bersama seluruh komoditasnya.

---

## Endpoint IKAN

Data trip perikanan: 10.619 trip, 17.900 baris tangkapan, 76.007 pengukuran biologi,
401 spesies, 9 provinsi / 3 WPPNRI / 27 alat tangkap.

Berbeda dari COAST, database ini **tidak punya kolom status/verifikasi**, jadi tidak ada
penyaringan seperti `verified` — semua baris memenuhi syarat.

### Dropdown berantai — `GET /api/v1/ext/ikan/opsi/{daftar}`

Enam daftar opsi untuk filter berantai. Setiap level bisa dipersempit oleh **setiap level
di atasnya**, sehingga dropdown tidak pernah menawarkan pilihan yang berujung kosong.

| Endpoint | Sumber | Parameter opsional |
|---|---|---|
| `opsi/wppnri` | `data_operasional_trip.WPPNRI` | — |
| `opsi/provinsi` | `data_identitas_trip.provinsi` | `wppnri` |
| `opsi/kabupaten` | `data_identitas_trip.kabupaten` | `wppnri`, `provinsi` |
| `opsi/lokasi-pendaratan` | `data_identitas_trip.lokasi_pendaratan` | + `kabupaten` |
| `opsi/jenis-data` | `data_identitas_trip.jenis_data` | + `lokasi_pendaratan` |
| `opsi/alat-tangkap` | `data_operasional_trip.alat_tangkap_utama` | + `jenis_data` |
| `opsi/family` | `data_tangkapan_biologi.family` | + `alat_tangkap` |
| `opsi/spesies` | `data_tangkapan_biologi.spesies` | + `family` |

```
GET /api/v1/ext/ikan/opsi/kabupaten?wppnri=WPPNRI-713&provinsi=SULAWESI%20SELATAN
```

```json
{
  "data": [
    { "value": "PANGKAJENE DAN KEPULAUAN", "jumlah_trip": 1768 },
    { "value": "PANGKAJENE KEPULAUAN", "jumlah_trip": 768 }
  ]
}
```

- **Tidak dipaginasi.** Daftar terpanjang adalah 41 lokasi pendaratan.
- **Terurut abjad**, dan `jumlah_trip` menyebut berapa trip yang masih tercakup opsi itu
  **di bawah filter yang sudah dipilih** — berguna untuk label seperti "ACEH (1.719)".
- **Path pakai tanda hubung, parameter pakai garis bawah**: `opsi/lokasi-pendaratan`
  disaring dengan `?lokasi_pendaratan=`. Sama seperti `kawasan-konservasi` vs `desa_kode`.
- **Level boleh dilompati.** `opsi/alat-tangkap?provinsi=ACEH` sah, tanpa perlu mengisi
  kabupaten, lokasi, dan jenis data di antaranya.
- **Filter yang tidak berlaku diabaikan, bukan ditolak.** Klien boleh menyimpan seluruh
  state filternya dan mengirimkannya ke setiap level; `opsi/wppnri?provinsi=ACEH` tetap
  mengembalikan seluruh WPPNRI, karena `provinsi` berada di bawahnya.
- **Nilai kosong sama dengan tanpa filter.** Dropdown yang direset mengirim `provinsi=`,
  dan itu berarti "semua", bukan "provinsi bernama string kosong".

Rantainya didefinisikan satu kali di `IkanFilterService::CHAIN`, yang menentukan
sekaligus daftar apa yang tersedia, filter apa yang diterima masing-masing, dan tabel
mana yang perlu di-join — menambah level baru berarti satu baris di sana.

### Grafik trip — `GET /api/v1/ext/ikan/grafik/trip`

Dua seri sekaligus dalam satu respons: jumlah trip **per periode pendataan** dan **per
lokasi pendaratan**.

Sengaja satu endpoint, bukan dua. Keduanya harus menggambarkan himpunan trip yang sama;
kalau dipisah, klien yang lupa meneruskan satu filter ke salah satunya akan mendapat dua
chart yang diam-diam bertentangan.

```
GET /api/v1/ext/ikan/grafik/trip?provinsi=ACEH&tipe_tanggal=monthly&dari=2025-01-01&sampai=2025-12-31
```

```json
{
  "data": {
    "filter": { "tipe_tanggal": "monthly", "dari": "2025-01-01", "sampai": "2025-12-31", "provinsi": "ACEH" },
    "total_trip": 1209,
    "per_tanggal": [
      { "periode": "2025-01", "jumlah_trip": 102 },
      { "periode": "2025-02", "jumlah_trip": 143 }
    ],
    "per_lokasi_pendaratan": [
      { "lokasi_pendaratan": "PPI UJONG BAROH", "jumlah_trip": 445 }
    ]
  }
}
```

| Parameter | Nilai |
|---|---|
| `wppnri`, `provinsi`, `kabupaten`, `lokasi_pendaratan`, `jenis_data` | opsional, semuanya kumulatif |
| `tipe_tanggal` | `monthly` (default) atau `yearly` |
| `dari`, `sampai` | `YYYY-MM-DD`, atas `tanggal_pendataan`, **inklusif di kedua ujung** |

- **Divalidasi, bukan dipaksakan diam-diam.** `tipe_tanggal=bulanan` atau
  `dari=01/02/2026` menjawab `422` dengan menyebut parameternya. Chart yang dibangun dari
  parameter yang salah diam-diam akan terlihat masuk akal tapi keliru, dan pemanggilnya
  tidak punya cara tahu.
- **`filter` dipantulkan kembali**, jadi respons yang tersimpan di cache tetap bisa
  dijelaskan tanpa merujuk URL-nya.
- `per_tanggal` terurut dari terlama; `per_lokasi_pendaratan` terurut **abjad menurut
  nama lokasi**, sama dengan urutan `opsi/lokasi-pendaratan`, sehingga sumbu chart dan
  dropdown yang menyaringnya terbaca dalam urutan yang sama.
- **Filter `alat_tangkap`, `family`, dan `spesies` tidak diterima di sini.** Chart ini
  menghitung trip, sementara ketiganya melipatgandakan trip ke banyak baris atau justru
  membuang trip yang tidak mencatat tangkapan.

#### Kapan periode kosong diisi nol

`per_tanggal` mengisi periode kosong dengan `0` **hanya bila `dari` dan `sampai`
keduanya diberikan**. Dengan rentang yang terbatas, sumbu waktu yang kontinu justru yang
dibutuhkan line chart, dan bulan tanpa trip memang bernilai nol, bukan tidak diketahui.

Tanpa batas rentang, hanya periode yang berisi data yang dikembalikan. Data asli
membentang dari satu trip di 2004 sampai hari ini: mengisinya penuh akan menghasilkan
**271 bulan, 212 di antaranya kosong**, dan chart-nya tidak terbaca.

---

### Grafik tangkapan — `GET /api/v1/ext/ikan/grafik/tangkapan`

Total berat tangkapan yang didaratkan, per spesies. Sumbernya
`data_tangkapan_catch` — **bukan** `data_tangkapan_biologi`, yang mengukur ikan satu per
satu dan membawa taksonomi berbeda.

```
GET /api/v1/ext/ikan/grafik/tangkapan?provinsi=ACEH&alat_tangkap=PANCING%20ULUR&dari=2025-01-01&sampai=2025-12-31
```

```json
{
  "data": {
    "filter": { "dari": "2025-01-01", "sampai": "2025-12-31", "provinsi": "ACEH", "alat_tangkap": "PANCING ULUR" },
    "unit": "kg",
    "total_catch": 40989,
    "per_spesies": [
      { "spesies": "Katsuwonus pelamis", "total_catch": 17387 },
      { "spesies": "Decapterus macarellus", "total_catch": 15460 }
    ]
  }
}
```

| Parameter | Nilai |
|---|---|
| `wppnri`, `provinsi`, `kabupaten`, `lokasi_pendaratan`, `jenis_data`, `alat_tangkap` | opsional |
| `dari`, `sampai` | `YYYY-MM-DD`, atas `tanggal_pendataan`, **inklusif di kedua ujung** |

- **Tidak dipaginasi**, terurut dari **terberat**: 401 spesies, dan pertanyaan yang
  dijawab endpoint ini adalah "tangkapannya terdiri dari apa", yang dibaca dari atas.
- **Menerima `alat_tangkap`**, berbeda dari grafik trip. Menjumlahkan berat tidak
  terpengaruh oleh satu trip yang muncul di banyak baris tangkapan, sedangkan menghitung
  trip akan terpengaruh.
- **Tidak ada `tipe_tanggal`** di sini — keluarannya bukan deret waktu.
- `unit` selalu `kg`, disebutkan di respons karena kolom hulunya tidak menyatakan satuan.

#### `alat_tangkap` di sini berarti alat tangkap **trip**-nya

Tabel tangkapan punya kolom `alat_tangkap_utama` sendiri, dan isinya **berbeda dari
alat tangkap trip pada 1.110 dari 17.900 baris**. Keduanya bahkan tidak memakai
kosakata yang sama: tabel tangkapan mengenal dua alat yang tidak ada di tabel
operasional (`JARING INSANG BERLAPIS`, `JARING LINGKAR`) dan tidak punya lima yang ada
di sana.

Filter ini membaca **kolom tabel operasional**, sumber yang sama dengan
`opsi/alat-tangkap`. Kalau tidak, lima opsi dropdown akan selalu mengembalikan kosong.
Jadi `alat_tangkap=X` berarti **"didaratkan oleh trip yang alat tangkap utamanya X"**,
bukan "ditangkap dengan X". Untuk sebagian besar keperluan keduanya sama; untuk 6% baris
itu tidak.

#### Spesies tanpa berat tercatat tidak ditampilkan

Tiga baris di hulu tidak punya `total_catch`. Spesies yang **seluruh** barisnya begitu
akan menjumlah jadi `NULL`, dan menampilkannya sebagai `0 kg` berarti menyatakan ia
ditimbang dan hasilnya nol — padahal ia tidak ditimbang sama sekali. Spesies seperti itu
dikeluarkan dari daftar. Saat ini tidak ada satu pun yang terkena: 401 spesies tetap
utuh.

---

### Grafik frekuensi panjang — `GET /api/v1/ext/ikan/grafik/frekuensi-panjang`

Sebaran panjang ikan yang diukur (`data_tangkapan_biologi.panjang_total`, 76.007 ikan),
dengan lebar selang kelas yang bisa diatur dan indikator yang biasa dibaca bersamanya.

```
GET /api/v1/ext/ikan/grafik/frekuensi-panjang?spesies=Katsuwonus%20pelamis&tipe_panjang=FL&selang_kelas=2&lm=40
```

```json
{
  "data": {
    "filter": { "tipe_panjang": "FL", "spesies": "Katsuwonus pelamis" },
    "unit": "cm",
    "selang_kelas": 2,
    "ringkasan": {
      "jumlah_ikan": 8439, "panjang_min": 14, "panjang_maks": 80,
      "rata_rata": 32.4, "median": 31.61, "modus": 31
    },
    "komposisi_tipe_panjang": [{ "tipe_panjang": "FL", "jumlah": 8439 }],
    "indikator": {
      "lc": 28.17,
      "lc_metode": "interpolasi 50% frekuensi kumulatif pada limb naik hingga kelas modus",
      "lm": 40,
      "persen_di_bawah_lm": 82.66
    },
    "kelas": [
      { "batas_bawah": 30, "batas_atas": 32, "nilai_tengah": 31, "jumlah": 1184, "persen": 14.03, "kumulatif_persen": 52.71 }
    ]
  }
}
```

| Parameter | Nilai |
|---|---|
| `wppnri`, `provinsi`, `kabupaten`, `lokasi_pendaratan`, `jenis_data`, `alat_tangkap`, `family`, `spesies` | opsional |
| `dari`, `sampai` | `YYYY-MM-DD`, atas `tanggal_pendataan`, inklusif di kedua ujung |
| `tipe_panjang` | `TL` atau `FL`. **Kosong berarti keduanya** |
| `selang_kelas` | lebar kelas, 0.1–50, default `1` |
| `lm` | panjang matang gonad, opsional — lihat di bawah |

- **Kelas dikembalikan berurutan dan rapat**, termasuk kelas yang kosong. Lubang pada
  sumbu akan terbaca sebagai sebaran bimodal, bukan sebagai satu batang yang hilang.
- **`ringkasan` dihitung dari baris aslinya**, bukan dari kelas: mengubah `selang_kelas`
  tidak menggeser `panjang_min`, `panjang_maks`, atau `rata_rata`. Yang bergantung pada
  lebar kelas hanya `median`, `modus`, dan `lc`.
- Panjang bernilai `0` bukan pengukuran dan tidak ikut dihitung.

#### Lc dihitung, Lm harus Anda pasok

**`lc` adalah estimasi deskriptif, bukan hasil pencocokan kurva selektivitas.** Ia
adalah panjang pada 50% frekuensi kumulatif **limb naik** — kelas-kelas sampai dengan
kelas modus — diinterpolasi di dalam kelasnya, yaitu metode data terkelompok pada Sparre
& Venema. Respons selalu menyertakan `lc_metode` supaya angkanya tidak salah dibaca
sebagai keluaran regresi logistik, yang butuh data selektivitas alat tangkap dan tidak
ada di database ini.

**`lm` tidak bisa dihitung dari database ini sama sekali.** Panjang matang gonad adalah
parameter biologi sebuah spesies, bukan sesuatu yang tersingkap oleh sampel frekuensi
panjang, dan tidak ada tabel rujukan di sini yang menyimpannya. Karena itu ia menjadi
**masukan**: pasok dari literatur untuk spesies yang sedang Anda filter, dan
`persen_di_bawah_lm` akan ikut kembali — dihitung eksak dari baris, bukan diinterpolasi
dari kelas. Tanpa `lm`, keduanya `null`.

Perbandingan Lc terhadap Lm itulah bacaan utamanya: `lc` di bawah `lm` berarti perikanan
menangkap ikan sebelum sempat memijah.

#### Jangan campur TL dan FL tanpa sadar

`tipe_panjang` kosong berarti **semua** tipe ikut, sesuai permintaan. Tapi *fork length*
dan *total length* mengukur hal yang berbeda: FL 20 dan TL 20 bukan ikan yang sama.
Sebagian spesies memang direkam dengan kedua cara — Variola albimarginata punya 4.097
TL, 979 FL, dan 193 tanpa tipe — sehingga histogram gabungannya melebar secara semu.

Karena itu `komposisi_tipe_panjang` **selalu** ada di respons: dari situ terlihat kapan
sebuah histogram mencampur dua pengukuran. Pada contoh Variola di atas, `lc` gabungan
27,71 berada di antara TL-saja 28,02 dan FL-saja 24,90 — bukan angka yang mewakili
keduanya. Untuk analisis per spesies, sebaiknya `tipe_panjang` selalu diisi.

Catatan: 311 baris di hulu tidak mencatat tipe panjang sama sekali; baris itu muncul
sebagai `"tipe_panjang": null` pada komposisi, dan hanya ikut saat `tipe_panjang`
dikosongkan.

---

#### Dua hal khusus pada `family` dan `spesies`

Kedua level ini berasal dari `data_tangkapan_biologi`, yang **satu trip bisa punya
banyak barisnya** (76.007 baris untuk 9.247 trip) dan **tidak mencakup semua trip**
(1.372 trip tidak punya catatan biologi sama sekali). Dua konsekuensinya:

- **`jumlah_trip` menghitung trip, bukan ikan yang diukur.** Satu trip yang mengukur
  spesies yang sama tiga kali tetap dihitung satu.
- **Trip tanpa catatan biologi tetap terhitung di level di atas `family`.** Tabel biologi
  hanya di-join ketika `family`/`spesies` sedang didaftar atau dijadikan filter; kalau
  di-join selalu, sebuah provinsi akan melaporkan trip lebih sedikit daripada yang
  dimilikinya. Di level `family` ke bawah, trip itu memang tidak muncul — ia tidak
  mengukur apa pun, jadi tidak ada family yang bisa menaunginya.

> Catatan data: `data_tangkapan_catch` juga punya kolom `family` dan `spesies`, dengan
> cakupan berbeda (61 family / 401 spesies atas 8.918 trip, versus 49 / 374 atas 9.247
> trip di tabel biologi). Endpoint ini memakai **tabel biologi**, dan itu pilihan yang
> disengaja. Kalau dropdown-nya kelak dipakai untuk menyaring data tangkapan (berat per
> spesies), sumber yang cocok adalah tabel catch, bukan ini.

#### Nilai duplikat sengaja tidak dinormalisasi

`kabupaten` memuat **"PANGKAJENE DAN KEPULAUAN" (1.768 trip) dan "PANGKAJENE KEPULAUAN"
(768 trip)** — kabupaten yang sama dengan dua ejaan, sehingga muncul sebagai dua opsi
terpisah di dropdown.

Ini **tidak** digabungkan di sini, atas keputusan pemilik produk: membiarkannya terlihat
adalah cara admin menyadari masalahnya dan memperbaikinya di hulu, sedangkan
menormalisasinya di API justru menyembunyikan masalah itu selamanya sambil menambah
daftar alias yang harus dirawat. Perlakukan hal serupa yang muncul kemudian dengan cara
yang sama — perbaikannya ada di data, bukan di sini.

---

## Endpoint BSC

Data pendaratan rajungan dan kepiting: **4.904 record induk** di `data_trip` (4.864
berlabel `TRIP`, 40 `NON TRIP`) dan **47.143 pengukuran individu** di `data_biologi`.

Kebutuhannya sama dengan IKAN — dropdown berantai, grafik trip, komposisi tangkapan,
frekuensi panjang — tetapi skemanya berbeda dan perbedaannya mengubah jawabannya:

| | IKAN | BSC |
|---|---|---|
| Level teratas rantai | WPPNRI | **provinsi** (tidak ada WPPNRI sama sekali) |
| Level rantai | 8 | **7** (tidak ada `family`) |
| Tangkapan per spesies | tabel `data_tangkapan_catch` | **tidak ada** — hanya bobot individu |
| Ukuran | `panjang_total` + tipe TL/FL | **`lebar_karapas`** + `jenis_kelamin` |
| Lm | harus dipasok dari literatur | **dihitung dari `TKG`** |

### Cakupan: hanya `trip_nontrip = 'TRIP'`

Setiap endpoint BSC hanya membaca record berlabel `TRIP`. Ke-40 record `NON TRIP`
pengukurannya ada di tabel `data_nontrip`, yang **tidak dibaca sama sekali**.

Aturan ini bukan formalitas: **669 baris `data_biologi` justru menempel pada record
`NON TRIP`** dan ikut tersaring, ditambah **381 baris yatim** yang `id_trip`-nya tidak
ada di `data_trip`. Yang tersisa 46.093 pengukuran dari 4.864 trip.

### Dropdown berantai — `GET /api/v1/ext/bsc/opsi/{daftar}`

| Endpoint | Sumber | Parameter opsional |
|---|---|---|
| `opsi/provinsi` | `data_trip.provinsi` | — |
| `opsi/kabupaten` | `data_trip.kabupaten` | `provinsi` |
| `opsi/lokasi-pendaratan` | `data_trip.lokasi_pendaratan` | + `kabupaten` |
| `opsi/jenis-pendataan` | `data_trip.jenis_pendataan` | + `lokasi_pendaratan` |
| `opsi/alat-tangkap` | `data_trip.alat_tangkap` | + `jenis_pendataan` |
| `opsi/jenis-tangkapan` | `data_trip.jenis_tangkapan` | + `alat_tangkap` |
| `opsi/spesies` | `data_biologi.spesies` | + `jenis_tangkapan` |

Bentuk respons, urutan abjad, `jumlah_trip`, dan perlakuan filter yang tidak berlaku
persis sama dengan rantai IKAN. `jumlah_trip` menghitung **trip**, bukan individu: satu
trip dengan lima puluh rajungan terukur tetap dihitung satu.

> Catatan data: `kabupaten` memuat `DEMAK` dan `KABUPATEN DEMAK`, juga `JEPARA` dan
> `KABUPATEN JEPARA` — kabupaten yang sama dengan dua ejaan, muncul sebagai opsi
> terpisah. Sengaja tidak dinormalisasi, alasan yang sama dengan Pangkajene di IKAN.

### `GET /api/v1/ext/bsc/grafik/trip`

Identik bentuknya dengan `ikan/grafik/trip`: `per_tanggal` (`tipe_tanggal` `monthly`
default atau `yearly`, periode kosong diisi nol hanya bila rentang dibatasi) dan
`per_lokasi_pendaratan` terurut abjad. Tanggalnya `data_trip.tanggal`.

Filter: `provinsi`, `kabupaten`, `lokasi_pendaratan`, `jenis_pendataan`, `alat_tangkap`,
`jenis_tangkapan`, `dari`, `sampai`. **`spesies` tidak diterima** — ia ada di tabel
individu, dan menggabungkannya akan mengubah hitungan trip menjadi hitungan rajungan.

### `GET /api/v1/ext/bsc/grafik/tangkapan`

Komposisi per spesies berdasarkan **bobot individu yang diukur**
(`sum(data_biologi.bobot)`).

**Ini sampel, bukan pendaratan.** BSC tidak punya tabel tangkapan per spesies:
`data_trip` hanya menyimpan total per trip tanpa rincian spesies
(`total_tangkapan_utama` seluruhnya 22.115), sementara rajungan yang benar-benar
ditimbang berjumlah sekitar 6,63 juta gram. Dua populasi berbeda dengan satuan berbeda.
Baca endpoint ini sebagai "tangkapan yang diukur terdiri dari apa", bukan "berapa yang
didaratkan".

Satuan `gram` — hulu tidak menyatakannya; rata-rata sekitar 145 atas 46.093 rajungan
dengan maksimum 1.800. Spesies yang seluruh individunya tidak ditimbang dikeluarkan,
bukan dilaporkan 0.

### `GET /api/v1/ext/bsc/grafik/frekuensi-lebar`

Sebaran lebar karapas, dengan Lc **dan Lm** yang keduanya dihitung.

| Parameter | Nilai |
|---|---|
| ketujuh level rantai | opsional |
| `dari`, `sampai` | `YYYY-MM-DD` atas `data_trip.tanggal`, inklusif |
| `jenis_kelamin` | `JANTAN` atau `BETINA`. Kosong berarti keduanya |
| `selang_kelas` | 0.1–50, default `1` |
| `tkg_matang` | 1–3, default `2` — lihat di bawah |

Setiap kelas membawa `jumlah`, `jumlah_matang`, dan `persen_matang`, sehingga kurva
kematangan bisa digambar dan Lm di bawah bisa diperiksa sendiri.

#### Lm dihitung dari TKG — dan ambangnya sebuah keputusan

Berbeda dari IKAN, BSC mencatat tingkat kematangan gonad per individu, jadi Lm tidak
perlu dipasok. Yang tetap perlu ditentukan adalah **stadium mana yang berarti matang**,
dan itu penilaian biologis yang tidak dinyatakan datanya. Karena itu `tkg_matang` adalah
parameter, dengan default `2`:

| `tkg_matang` | Persen matang | Lm (Portunus pelagicus betina) |
|---|---|---|
| 1 | 96,5% | 7,5 — semuanya "matang", angkanya runtuh ke kelas terkecil |
| **2** | **62,7%** | **9,49** — kurva S yang wajar, sesuai kisaran literatur |
| 3 | 11,1% | `null` — tidak ada kelas lebar yang menembus 50% |

Default `3` akan mematikan indikatornya sama sekali, dan `1` membuatnya tak bermakna.
Tetap perlu dikonfirmasi ke pemilik data.

`lm` diinterpolasi pada lebar saat proporsi matang pertama kali menembus setengah,
antara dua titik tengah kelas yang mengapitnya. **Kelas dengan kurang dari 10 individu
dilewati**: di ekor sebaran, tiga rajungan bisa membaca 100% dan menarik Lm ke tempat
data paling jarang. Seperti Lc, ini estimasi deskriptif, bukan regresi logistik —
`lm_metode` menyatakannya di respons.

Lc di bawah Lm berarti perikanan menangkap rajungan sebelum sempat memijah.

#### Empat kosakata untuk dua jenis kelamin

`jenis_kelamin` di hulu ditulis sebagai `JANTAN`/`M`/`L` dan `BETINA`/`F`/`P`, ditambah
83 baris yang bukan keduanya (kosong, atau `"2"`). Nilainya **dinormalkan** jadi dua:
JANTAN 26.016 dan BETINA 21.044; sisanya jadi `null` dan hanya ikut saat
`jenis_kelamin` dikosongkan.

`komposisi_jenis_kelamin` selalu ada di respons supaya hasil normalisasi dan sisanya
tetap terlihat.

---

## Endpoint HIUPARI

Data pendaratan hiu dan pari: 4.579 trip dan **19.268 individu terukur**. Permukaannya
sengaja sempit — satu daftar spesies dan satu histogram yang menyaringnya.

### `GET /api/v1/ext/hiupari/opsi/spesies`

Dua puluh spesies, terurut abjad, tanpa paginasi dan **tanpa rantai** (tidak ada level
lain untuk menyaringnya).

```json
{ "data": [ { "value": "Alopias pelagicus", "jumlah_individu": 116 } ] }
```

`jumlah_individu` menghitung **semua** individu spesies itu, bukan hanya yang punya
ukuran tertentu — daftar ini menyatakan spesies apa yang ada, bukan kolom mana yang
kebetulan terisi.

### `GET /api/v1/ext/hiupari/grafik/frekuensi-panjang`

| Parameter | Nilai |
|---|---|
| `spesies` | opsional; kosong berarti semua |
| `jenis_kelamin` | `M` atau `F`; kosong berarti keduanya |
| `jenis_ukuran` | `panjang_total` (default), `precaudal_length`, `fork_length`, `predorsal_length`, `panjang_headless` |
| `selang_kelas` | 0.1–50, default `1` |
| `kematangan_matang` | 1–3, default `3` — lihat Lm di bawah |

```json
{
  "data": {
    "filter": { "spesies": "Rhynchobatus australiae" },
    "jenis_ukuran": "panjang_total",
    "unit": "cm",
    "selang_kelas": 10,
    "ringkasan": {
      "jumlah_individu": 7557, "jumlah_tanpa_ukuran": 1,
      "panjang_min": 34, "panjang_maks": 336,
      "rata_rata": 109.54, "median": 101.42, "modus": 85
    },
    "ketersediaan_ukuran": [
      { "jenis_ukuran": "precaudal_length", "jumlah_individu": 7558 },
      { "jenis_ukuran": "panjang_total", "jumlah_individu": 7557 },
      { "jenis_ukuran": "fork_length", "jumlah_individu": 2804 }
    ],
    "indikator": {
      "linf": 353.68,
      "linf_metode": "empiris Lmax / 0.95 (Froese & Binohlan 2000)",
      "lm": null,
      "lm_metode": "hanya tersedia untuk jantan: kematangan klasper adalah ciri jantan, dan betina tercatat 0 karena tidak berklasper",
      "persen_matang": null
    },
    "kelas": [ { "batas_bawah": 80, "batas_atas": 90, "nilai_tengah": 85, "jumlah": 812, "persen": 10.75, "kumulatif_persen": 41.2 } ]
  }
}
```

#### Kenapa `jenis_ukuran` wajib ada, bukan sekadar tambahan

Hiu dan pari diukur dengan **lima cara berbeda**, dan kelengkapannya berbeda jauh antar
spesies:

| Spesies | individu | `panjang_total` | `precaudal_length` | `predorsal_length` |
|---|---:|---:|---:|---:|
| Rhynchobatus australiae | 7.558 | 7.557 | 7.558 | 0 |
| Alopias superciliosus | 212 | 56 | 133 | — |
| **Prionace glauca** | **247** | **21** | 30 | **88** |

Mengunci satu kolom akan menggambar histogram hiu biru dari **21 dari 247 individu**
(8%) — dan grafiknya tidak akan terlihat berbeda dari yang memakai seluruhnya. Karena
itu dua hal selalu ada di respons:

- **`ringkasan.jumlah_tanpa_ukuran`** — berapa individu dalam cakupan yang tidak punya
  ukuran yang dipilih. Untuk Prionace glauca dengan `panjang_total`, angkanya 226.
- **`ketersediaan_ukuran`** — berapa individu yang punya masing-masing dari lima ukuran,
  terurut dari yang terbanyak. Dari situ terlihat bahwa untuk hiu biru,
  `predorsal_length` justru memberi 88 individu, empat kali lipat `panjang_total`.

Default-nya `panjang_total` karena total length adalah ukuran pelaporan konvensional
untuk hiu dan pari — **bukan** karena ia yang paling lengkap.

Satuan `cm` disimpulkan dari sebarannya (5–392); hulu tidak menyatakannya.

#### Linf — estimasi empiris, bukan kurva pertumbuhan

`linf` adalah hubungan empiris Froese & Binohlan (2000): **Lmax / 0.95**. Sederhana dan
kokoh, tapi perlu dibaca apa adanya:

- **Bertumpu pada satu nilai ekstrem.** Satu salah input raksasa akan menggesernya.
- **Mengikuti seleksi, bukan spesies.** Untuk *Rhynchobatus australiae*, jantan memberi
  300,00 (Lmax 285) dan betina 353,68 (Lmax 336) — keduanya benar untuk sampelnya
  masing-masing.
- **Hubungan itu dirumuskan untuk total length.** Dipakai pada `precaudal_length` atau
  `predorsal_length` ia tetap mengembalikan angka, tapi angka itu asimtot dari ukuran
  tersebut, bukan dari hewannya. `jenis_ukuran` selalu ikut di respons karena itu.

Ini bukan hasil pencocokan kurva von Bertalanffy. Metode berbasis frekuensi panjang
seperti Powell-Wetherall memberi angka berbeda — untuk *R. australiae* 417,42 — dan bisa
ditambahkan bila diperlukan.

#### Lm — dihitung dari kematangan klasper, **hanya untuk jantan**

`lm` adalah panjang saat setengah individu mencapai tahap klasper `kematangan_matang`,
diinterpolasi antara dua titik tengah kelas yang mengapitnya, dengan kelas berisi kurang
dari 10 individu dilewati.

**Ia `null` kecuali `jenis_kelamin=M`.** Kematangan klasper adalah ciri jantan:
**12.091 dari 12.108 betina tercatat 0**, yang berarti "tidak berklasper", bukan "belum
matang". Menghitung ogive atas sampel campuran akan terbaca seolah hampir tidak ada yang
pernah memijah. `lm_metode` menyatakan alasannya di respons.

Contoh nyata, *R. australiae* jantan pada `kematangan_matang=3`: ogive 4,8% → 12,9% →
17,4% → **48,1%** → 65,8% → 78,8%, sehingga **Lm = 116,06**.

**158 baris di hulu mencatat `kematangan_klasper` bernilai 5 sampai 37** — panjang
klasper yang tertulis di kolom kematangan. Nilai di luar skala 0–3 diperlakukan sebagai
tidak bertahap, bukan sebagai hewan yang sangat matang.

## Endpoint STSC

Statistik perikanan nasional: **352 baris** di `data_armada` (satu baris per tahun per
WPPNRI) dan **3.872 baris** di `data_produksi` (tahun × WPPNRI × komoditas). Keduanya
1990–2021, 11 WPPNRI, 11 komoditas, tanpa baris ganda.

Berbeda dari tiga datasource perikanan lain di dokumen ini, **ini bukan data pendaratan**:
tidak ada trip, tidak ada individu terukur, tidak ada tanggal. Yang ada adalah angka
agregat yang sudah dipublikasikan pihak hulu. Konsekuensinya tiga:

- **tidak ada dropdown berantai.** WPPNRI dan komoditas adalah dua sumbu dari satu grid,
  bukan hierarki. `opsi/komoditas` tetap menerima `wpp` — karena orang yang sudah memilih
  wilayah ingin tahu apa yang didaratkan di sana — tapi tidak ada urutan level.
- **filternya tahun, bukan tanggal.** `dari_tahun`/`sampai_tahun`, inklusif di kedua ujung.
- **endpoint ini tidak menghitung apa-apa** selain penjumlahan. Tidak ada indikator, tidak
  ada interpolasi; angkanya adalah angka hulu, disajikan ulang dalam bentuk seri.

`wpp` divalidasi terhadap **sebelas kode WPPNRI** (Permen KP 18/2014) yang tercatat di
kedua tabel. `wpp=999` menjawab `422`, bukan grafik kosong — grafik kosong terbaca sebagai
"tidak ada armada di sana", yang merupakan pernyataan yang sama sekali berbeda.

### `GET /api/v1/ext/stsc/opsi/wpp`

Sebelas wilayah, urut natural, tanpa paginasi.

```json
{ "data": [ { "value": "571", "tahun_awal": 1990, "tahun_akhir": 2021, "sumber": ["armada", "produksi"] } ] }
```

`sumber` menyebut tabel mana saja yang memuat wilayah itu. Kedua tabel diisi terpisah di
hulu: wilayah yang ada di `produksi` tapi tidak di `armada` akan menggambar grafik armada
kosong dan grafik produksi penuh, dan daftar ini mengatakannya sebelum grafiknya
mengatakannya. Pada data sekarang kesebelasnya ada di keduanya.

### `GET /api/v1/ext/stsc/opsi/komoditas`

Sebelas komoditas, urut abjad — urutan yang sama dengan `grafik/produksi`, jadi sumbu dan
dropdown yang menyaringnya terbaca sama. Menerima `wpp` opsional.

```json
{ "data": [ { "value": "Cumi-Cumi", "jumlah_wpp": 11, "tahun_awal": 1990, "tahun_akhir": 2021 } ] }
```

`jumlah_wpp` adalah banyaknya wilayah yang mendaratkan komoditas itu — sekaligus banyaknya
garis yang akan digambar grafiknya.

> Catatan data: `Rajungan`, `Kepiting`, dan `Rajungan-Kepiting` ketiganya ada sebagai
> komoditas terpisah. **Jangan menjumlahkan ketiganya**; kategori gabungan itu tumpang
> tindih dengan dua lainnya. Sengaja tidak dinormalisasi, alasan yang sama dengan
> Pangkajene di IKAN.

### Grafik armada — `GET /api/v1/ext/stsc/grafik/armada`

| Parameter | Nilai |
|---|---|
| `wpp` | salah satu dari 11 kode; kosong berarti semua |
| `dari_tahun` | opsional, 1900–2100 |
| `sampai_tahun` | opsional, 1900–2100, tidak boleh mendahului `dari_tahun` |

Jumlah kapal dan total tonase dalam **satu respons**, bukan dua endpoint: keduanya dibaca
saling-silang — armada menyusut sementara tonasenya naik adalah inti grafik ini — dan dua
panggilan bisa saja menjawab dari dua filter yang berbeda.

```json
{
  "data": {
    "filter": { "wpp": "571", "dari_tahun": 2019, "sampai_tahun": 2021 },
    "unit": { "armada": "unit", "gt": "GT" },
    "tahun": [2019, 2020, 2021],
    "armada": [
      { "wpp": "571", "titik": [ { "tahun": 2019, "nilai": 50504 }, { "tahun": 2020, "nilai": 66998 } ] }
    ],
    "gt": [
      { "wpp": "571", "titik": [ { "tahun": 2019, "nilai": 250782 }, { "tahun": 2020, "nilai": 277145 } ] }
    ]
  }
}
```

`tahun` adalah sumbu-x gabungan seluruh seri, supaya klien yang menggambar sebelas garis
tidak perlu menghitung irisannya sendiri.

#### Tahun yang tidak dilaporkan dikosongkan, bukan dinolkan

`titik` hanya memuat tahun yang benar-benar punya baris. Berbeda dengan `ikan/grafik/trip`
dan `bsc/grafik/trip` yang mengisi periode kosong dengan nol pada rentang terbatas: di
sana nol berarti "tidak ada trip", di sini nol akan berarti "armadanya hilang" — klaim
yang sama sekali lain dari "tidak dilaporkan".

#### Tidak ada total

Tidak ada `total_armada` maupun `total_gt`, dan itu disengaja. Keduanya **stok** yang
dihitung ulang setiap tahun; menjumlahkan 1990 sampai 2021 melaporkan armada yang jauh
lebih besar daripada yang pernah ada. Bandingkan dengan produksi, yang merupakan **aliran**
dan karenanya memang ditotal.

### Grafik produksi — `GET /api/v1/ext/stsc/grafik/produksi`

| Parameter | Nilai |
|---|---|
| `wpp` | salah satu dari 11 kode; kosong berarti semua |
| `komoditas` | opsional; kosong berarti semua |
| `dari_tahun`, `sampai_tahun` | sama dengan grafik armada |

```json
{
  "data": {
    "filter": { "wpp": null, "komoditas": "Rajungan", "dari_tahun": 2019, "sampai_tahun": 2021 },
    "unit": "ton",
    "tahun": [2019, 2020, 2021],
    "total_produksi_ton": 317463.64,
    "komoditas": [
      {
        "komoditas": "Rajungan",
        "total_produksi_ton": 317463.64,
        "seri": [
          { "wpp": "571", "titik": [ { "tahun": 2019, "nilai": 2833.5 }, { "tahun": 2020, "nilai": 3068.38 } ] }
        ]
      }
    ]
  }
}
```

Bersarang per komoditas, lalu per WPPNRI — satu panel per komoditas, sebelas garis di
tiap panel. Menyaring dengan `wpp` **tidak mengubah bentuknya**, hanya menyisakan satu
garis per panel, jadi klien tidak perlu bercabang berdasarkan filter yang kebetulan ia
kirim.

Angka produksi dijumlahkan per (tahun, WPPNRI, komoditas). Pada data sekarang penjumlahan
itu tidak melakukan apa-apa — satu baris per kunci — dan tetap benar kalau suatu saat hulu
memecah satu angka menjadi dua baris.

### Warna garis bukan urusan API

Tidak ada `color` di respons mana pun, termasuk yang ini. Palet per WPPNRI adalah keputusan
tampilan, bukan data: ia berubah saat tema situs berubah, ia berbeda antara grafik dan peta,
dan menaruhnya di sini berarti mendeploy backend untuk mengganti satu warna. Peta
`wpp => warna` tinggal jadi konstanta di front-end, dengan `value` dari `opsi/wpp` sebagai
kuncinya.

---

## Read-only: bagaimana ditegakkan

Tiga lapis, dari luar ke dalam:

1. **Grant database** — user `SELECT`-only. Satu-satunya lapis yang tidak bisa dilewati bug
   di kode kita, sekaligus satu-satunya yang tidak terlihat dari repositori ini.
2. **Guard koneksi** (`DatasourceServiceProvider`) — setiap statement pada koneksi datasource
   dicek sebelum dieksekusi dengan **allow-list** (`select`, `show`, `describe`, `desc`,
   `explain`, `with`, `set`). Allow-list, bukan block-list: block-list hanya selengkap dialek
   SQL yang sempat diingat, dan ongkos salahnya adalah tulisan ke database produksi orang lain.
   Lapis ini menjaring Eloquent, query builder, dan `DB::statement()` sekaligus.
3. **Base model** (`ExternalModel`) — `performInsert`/`performUpdate`/`performDeleteOnModel`
   melempar `ReadOnlyDatasourceException` yang menyebut nama model, jadi jejak error-nya
   menunjuk ke kode kita, bukan ke error izin dari database milik tim lain.

Datasource yang memang perlu ditulis cukup menyetel `'read_only' => false`.

---

## Kenapa key-nya diberi prefix `ds_`

Koneksi Laravel-nya bernama `ds_<key>`, bukan `<key>`. Sebuah sumber yang kebetulan
diberi key `tenant` akan mengarahkan ulang **seluruh** model konten ke database asing
sementara setiap query tetap jalan tanpa keluhan. Prefix mencegah tabrakan itu, dan
`DatasourceRegistry::RESERVED` membuatnya fatal saat boot kalau sampai terjadi.

---

## Spesifikasi mesin

Kontrak yang sama dalam bentuk OpenAPI 3.0 ada di [openapi.yaml](openapi.yaml), satu
berkas bersama API publik — `/v1/*` dan `/v1/ext/*` memakai kunci yang sama, jadi
memisahkannya hanya akan membuat pembaca menebak yang mana yang berlaku.

Dijaga oleh `tests/Feature/Api/OpenApiSpecTest.php`: menambah route tanpa
mendokumentasikannya, atau meninggalkan path yang route-nya sudah dihapus, membuat
suite gagal. Spesifikasi hanya berguna kalau ia tidak bisa diam-diam menyimpang dari
aplikasinya.

---

## Berkas terkait

| Berkas | Isi |
|---|---|
| `config/datasources.php` | registry: prefix, defaults, TTL, rate limit, daftar sumber |
| `app/Services/DatasourceRegistry.php` | satu-satunya jalan resmi ke koneksi eksternal |
| `app/Providers/DatasourceServiceProvider.php` | pendaftaran koneksi + guard read-only |
| `app/Models/Concerns/ExternalModel.php` | base model (`App\Models\External\*`) |
| `app/Http/Controllers/ExtApi/ExternalApiController.php` | base controller + amplop respons |
| `app/Http/Middleware/EnsureDatasource.php` | `datasource:<key>` → 503 bila tidak tersedia |
| `routes/ext-api.php` | route `/api/v1/ext/*` |
| `app/Services/Coast/` | logika COAST: daftar desa, summary, resolver nama wilayah |
| `app/Services/Ikan/` | logika IKAN: rantai opsi filter |
| `app/Services/Bsc/` | logika BSC: rantai opsi, grafik trip, komposisi, frekuensi lebar |
| `app/Services/Hiupari/` | logika HIUPARI: daftar spesies dan frekuensi panjang |
| `app/Services/Stsc/` | logika STSC: opsi WPPNRI/komoditas, grafik armada dan produksi |
| `app/Models/External/Coast/` | model tabel COAST |
| `app/Console/Commands/DatasourcesStatus.php` | `php artisan cms:datasources` |
| `tests/Feature/Datasource/ExternalDatasourceTest.php` | uji registry, guard, middleware |
| `tests/Feature/Coast/CoastApiTest.php` | uji endpoint COAST ujung-ke-ujung |
| `tests/Feature/Ikan/IkanFilterApiTest.php` | uji rantai dropdown IKAN |
| `tests/Feature/Ikan/IkanTripChartApiTest.php` | uji grafik trip IKAN |
| `tests/Feature/Ikan/IkanCatchChartApiTest.php` | uji grafik tangkapan IKAN |
| `tests/Feature/Ikan/IkanLengthFrequencyApiTest.php` | uji grafik frekuensi panjang IKAN |
| `tests/Feature/Bsc/BscApiTest.php` | uji keempat permukaan BSC |
| `tests/Feature/Hiupari/HiupariApiTest.php` | uji kedua permukaan HIUPARI |
| `tests/Feature/Stsc/StscApiTest.php` | uji keempat endpoint STSC |
| `docs/openapi.yaml` | spesifikasi OpenAPI, publik + eksternal |
