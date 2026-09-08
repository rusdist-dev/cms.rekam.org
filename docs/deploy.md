# Panduan Deploy & Operasional

Dokumentasi ini melengkapi `plan.md` Fase 8. Berisi contoh konfigurasi dan skrip —
bukan file yang dieksekusi langsung dari repo ini, karena target server produksi
(VPS/shared hosting/dll) belum ditentukan. Sesuaikan path, user sistem, dan domain
sebelum dipakai.

## 1. Kebutuhan Server

- PHP 8.1 dengan ekstensi: `pdo_mysql`, `mbstring`, `bcmath`, `ctype`, `fileinfo`,
  `gd` (thumbnail via `intervention/image`), `xml`, `tokenizer`, `openssl`.
- Composer 2, Node 18+ (hanya untuk `npm run build` saat deploy — tidak perlu
  berjalan permanen di server).
- MySQL 8 atau MariaDB 10.4+ (lihat catatan kompatibilitas di `README.md`).
- Nginx + PHP-FPM.

## 2. Konfigurasi Nginx (contoh)

```nginx
server {
    listen 80;
    server_name cms.rekam.org;
    root /var/www/cms.rekam.org/public;

    index index.php;

    # config('cms.media.document.max_kb') = 20480 KB — beri sedikit ruang lebih.
    client_max_body_size 25m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # public/media/* ditulis langsung ke webroot (tanpa symlink storage:link —
    # lihat config/filesystems.php), jadi tidak perlu location block terpisah.

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Header keamanan (`X-Frame-Options`, CSP, dll.) **tidak perlu** diatur di Nginx —
sudah dipasang aplikasi lewat `App\Http\Middleware\SecurityHeaders` (global,
lihat `app/Http/Kernel.php`), supaya berlaku sama baik lewat Nginx atau server
lain saat development (`php artisan serve`).

Pasang HTTPS (Let's Encrypt / certbot) dan redirect port 80 → 443 di depan
konfigurasi ini seperti biasa.

## 3. `.env` Produksi

Salin `.env.example`, lalu ubah:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cms.rekam.org
```

`SESSION_DRIVER`/`CACHE_DRIVER` boleh tetap `file` untuk skala satu server; pindah
ke `redis` bila nanti berjalan di lebih dari satu server (session harus dibagi).

Karena Nginx ada di depan aplikasi (reverse proxy lokal), atur
`app/Http/Middleware/TrustProxies.php`:

```php
protected $proxies = '*';
```

Tanpa ini, `X-Forwarded-Proto` dari Nginx tidak dipercaya dan Laravel bisa salah
mendeteksi request sebagai HTTP meski sudah lewat HTTPS.

## 4. Langkah Deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force            # DB pusat
php artisan tenants:migrate --force    # DB tiap tenant

php artisan optimize                   # config + route + view cache
```

Catatan: `TenantManager::setCurrent()` memanggil `Config::set('database.connections.tenant.database', ...)`
saat runtime — ini tetap bekerja normal walau config sudah di-cache, karena yang
diubah adalah *repository* config yang sedang berjalan, bukan file cache-nya.

Tidak ada `php artisan storage:link` — upload ditulis langsung ke `public/`
(`config/filesystems.php`), bukan lewat symlink `storage/app/public`.

Setelah deploy berikutnya (kode berubah), ulangi langkah yang sama; bila hanya
config/route yang berubah cukup `php artisan optimize:clear && php artisan optimize`.

## 5. Queue Worker

Belum dibutuhkan hari ini — `QUEUE_CONNECTION=sync` di `.env.example`, dan tidak
ada job yang di-queue di manapun dalam aplikasi ini. Simpan sebagai referensi
bila nanti ada job yang di-antrekan (mis. pengiriman notifikasi massal):

```ini
# /etc/systemd/system/cms-queue.service
[Unit]
Description=CMS queue worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/cms.rekam.org
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3
Restart=always

[Install]
WantedBy=multi-user.target
```

## 6. Scheduler

`app/Console/Kernel.php` menjadwalkan `activitylog:clean` harian (menghapus baris
`activity_log` yang lebih tua dari `config('activitylog.delete_records_older_than_days')`,
saat ini 365 hari). Daftarkan satu entri cron standar Laravel:

```cron
* * * * * cd /var/www/cms.rekam.org && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Backup Database

Tidak memakai package tambahan — skrip `mysqldump` sederhana dijadwalkan lewat
cron, dump seluruh database pusat + setiap tenant (dibaca dari tabel `tenants`,
bukan daftar tetap, supaya tenant baru otomatis ikut ter-backup):

```bash
#!/usr/bin/env bash
# /opt/cms-backup/backup.sh
set -euo pipefail

BACKUP_DIR=/var/backups/cms
RETAIN_DAYS=14
mkdir -p "$BACKUP_DIR"

DATABASES=$(mysql -N -e "SELECT db_name FROM cms_central.tenants" ; echo cms_central)

for db in $DATABASES; do
    mysqldump --single-transaction "$db" | gzip > "$BACKUP_DIR/${db}_$(date +%Y%m%d_%H%M%S).sql.gz"
done

find "$BACKUP_DIR" -name '*.sql.gz' -mtime +"$RETAIN_DAYS" -delete
```

```cron
0 2 * * * /opt/cms-backup/backup.sh >> /var/log/cms-backup.log 2>&1
```

Uji pemulihan (`gunzip -c file.sql.gz | mysql db_name`) secara berkala — sebuah
backup yang tidak pernah dicoba dipulihkan bukan backup yang bisa diandalkan.

## 8. Menambah Tenant Baru (produksi)

Sama seperti langkah lokal di `README.md` §"Perintah Tenant", ditambah bagian
khusus produksi:

1. Buat database: `CREATE DATABASE cms_namabaru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Tambahkan entri tenant baru ke `database/seeders/TenantSeeder.php` (nama,
   domain, flag fitur awal), commit, deploy seperti biasa.
3. `php artisan db:seed --class=TenantSeeder --force`
4. `php artisan tenants:migrate --tenant=namabaru --seed --force`
5. Buat API key lewat halaman **Company** di dashboard (atau
   `php artisan tinker` → `$tenant->rotateApiKey()`), berikan ke tim compro.
6. Backup script §7 otomatis mengikutkan tenant ini pada jadwal berikutnya
   (dibaca dari tabel `tenants`, bukan daftar tetap).

## 9. Checklist Rilis

Sebelum menandai rilis selesai, semua berikut harus lolos (`context.md` §9 plus
tambahan Fase 8):

- [ ] `./vendor/bin/pint`, `php artisan test`, `npm run build` — semua hijau.
- [ ] `php artisan migrate --force` dan `tenants:migrate --force` sudah jalan
      tanpa error di server produksi.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `TrustProxies::$proxies` diatur bila di belakang reverse proxy (§3).
- [ ] Header keamanan terlihat di response (`curl -I` — cek `X-Frame-Options`,
      `Content-Security-Policy`, dst.).
- [ ] Cron `schedule:run` terpasang dan `activitylog:clean` benar-benar berjalan
      (cek log setelah 24 jam pertama).
- [ ] Skrip backup §7 diuji jalan sekali secara manual, dan hasil dump bisa
      di-restore.
- [ ] Rotasi API key untuk setiap tenant produksi dilakukan sekali di luar nilai
      development/staging.
