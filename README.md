# BroSeven! Shop

Aplikasi toko modern yang menyatukan **pemesanan produk oleh customer**, **pelayanan kasir (POS) dengan QR Code**, **manajemen stok**, dan **laporan bisnis**. Customer memesan dari beranda toko, menunjukkan QR Code ke kasir, lalu membayar langsung di kasir. Tidak ada payment gateway.

## Isi

- [Fitur](#fitur)
- [Teknologi](#teknologi)
- [Kebutuhan sistem](#kebutuhan-sistem)
- [Instalasi](#instalasi)
- [Konfigurasi (.env)](#konfigurasi-env)
- [Menjalankan aplikasi](#menjalankan-aplikasi)
- [Penjadwal (wajib untuk hapus otomatis draft)](#penjadwal-wajib-untuk-hapus-otomatis-draft)
- [Aturan bisnis](#aturan-bisnis)
- [Perintah Artisan](#perintah-artisan)
- [Pengujian](#pengujian)
- [Struktur proyek](#struktur-proyek)
- [Ringkasan API](#ringkasan-api)
- [Keamanan](#keamanan)
- [Checklist production](#checklist-production)
- [Pemecahan masalah](#pemecahan-masalah)

## Fitur

### Customer (tanpa akun)

- Beranda, katalog produk (cari, filter kategori dan merek, urut harga, terbaru, populer), detail produk.
- Keranjang yang harganya selalu dihitung ulang oleh server.
- Checkout, nomor pesanan unik (`BS-YYMMDD-XXXXX`), QR Code, halaman konfirmasi, cetak dan unduh bukti pesanan.
- Pelacakan status pesanan dengan nomor pesanan dan token akses yang aman. Pesanan DRAFT yang tidak divalidasi kasir **dihapus otomatis** setelah batas waktu (default 24 jam, dapat diatur).

### Kasir

- POS: pindai QR Code lewat kamera, input manual nomor pesanan atau kode (termasuk scanner USB), validasi barcode, setujui, selesaikan, batalkan dengan alasan.
- Retur barang (stok bertambah sesuai jumlah yang benar-benar diterima kembali).
- Dashboard, riwayat transaksi, laporan pribadi (PDF dan Excel), profil.

### Admin Master

- CRUD barang (kode otomatis, foto, harga, stok awal dan minimum) dan master data: kategori, tipe barang, merek, satuan.
- Penyesuaian stok dan riwayat stok. Laporan barang (PDF dan Excel). Dashboard inventori.

### Superadmin

- Dashboard (pendapatan, transaksi, produk terlaris, grafik), manajemen user, laporan pendapatan, barang, dan kasir (PDF, Excel, cetak).
- Monitoring pelayanan kasir (baca saja).
- Pengaturan: profil, **profil perusahaan** (nama, logo, favicon, kontak, footer, **batas hapus otomatis draft**), **warna website** dengan pratinjau langsung.

## Teknologi

| Lapisan | Pilihan |
|---|---|
| Backend | Laravel 13, PHP 8.3 atau lebih baru |
| Frontend | Vue 3 (Composition API), Vue Router, Pinia, Vite, CSS manual (tanpa framework CSS) |
| Database | MySQL 8.0.16+ (memakai CHECK constraint dan penguncian baris) |
| Autentikasi | Session cookie Laravel (route API berada di grup `web`, CSRF aktif) |
| PDF | `dompdf/dompdf` ^3 |
| Excel | `openspout/openspout` ^4.30 |
| QR | `qrcode` (membuat), `qr-scanner` (membaca lewat kamera) |

## Kebutuhan sistem

- PHP 8.3+ dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `xmlreader`, `libxml`, `dom`, `filter`. Ekstensi `gd` dibutuhkan agar logo tampil di PDF.
- Composer 2.
- Node.js 20.19+ atau 22.12+ dan npm.
- MySQL 8.0.16+ (atau MariaDB 10.2+).

## Instalasi

```bash
git clone <url-repositori> broseven-shop
cd broseven-shop

composer install
npm install

cp .env.example .env        # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

Buat database kosong (contoh `broseven_shop`, collation `utf8mb4_unicode_ci`), isi koneksi dan akun Superadmin di `.env` (lihat bagian berikut), lalu:

```bash
php artisan migrate --seed
php artisan storage:link
```

`--seed` membuat satu akun **Superadmin utama** dari variabel `SUPERADMIN_*` dan baris pengaturan awal (perusahaan dan warna). Seeder berhenti dengan pesan error jika `SUPERADMIN_PASSWORD` kosong. Tidak ada password bawaan di dalam kode. Akun Kasir dan Admin Master dibuat oleh Superadmin lewat menu **Manajemen User**.

## Konfigurasi (.env)

| Variabel | Keterangan |
|---|---|
| `APP_NAME`, `APP_URL`, `APP_ENV`, `APP_DEBUG` | Identitas dan mode aplikasi. Di production: `APP_ENV=production`, `APP_DEBUG=false` |
| `APP_LOCALE=id` | Bahasa |
| `DB_*` | Koneksi MySQL |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Default pengembangan: `file`, `file`, `sync` |
| `SUPERADMIN_NAME`, `SUPERADMIN_USERNAME`, `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | Akun Superadmin utama untuk seeder |
| `RETURN_WINDOW_DAYS` | Batas hari retur sejak transaksi selesai. `0` = tanpa batas |
| `REPORT_PDF_MAX_ROWS` | Batas baris export PDF dan cetak (default 1500) |
| `REPORT_EXCEL_MAX_ROWS` | Batas baris export Excel (default 20000) |

Atur juga zona waktu toko di `config/app.php` (`'timezone' => 'Asia/Jakarta'`, `Asia/Makassar`, atau `Asia/Jayapura`). Angka "hari ini" di dashboard dan laporan mengikuti zona waktu ini.

Batas hapus otomatis pesanan draft **tidak** diatur di `.env`, melainkan di **Pengaturan > Profil Perusahaan**.

## Menjalankan aplikasi

Mode pengembangan (dua terminal):

```bash
php artisan serve        # http://127.0.0.1:8000
npm run dev
```

Build untuk production:

```bash
npm run build
```

Alamat penting:

| Alamat | Keterangan |
|---|---|
| `/` | Beranda toko (customer) |
| `/login` | Masuk staf |
| `/superadmin/dashboard`, `/kasir/dashboard`, `/admin-master/dashboard` | Dashboard per role |

Kamera untuk memindai QR hanya berfungsi di **HTTPS atau localhost**. Akses lewat `http://192.168.x.x` diblokir oleh browser. Input manual dan scanner USB tetap bisa dipakai.

## Penjadwal (wajib untuk hapus otomatis draft)

Pesanan DRAFT yang belum divalidasi kasir dihapus oleh perintah `orders:purge-drafts`, yang dijadwalkan **tiap 10 menit**. Penjadwal Laravel harus berjalan, kalau tidak pesanan kedaluwarsa tidak akan terhapus.

- Pengembangan: `php artisan schedule:work` (biarkan terminal terbuka).
- Linux (cron):
  ```cron
  * * * * * cd /path/ke/broseven-shop && php artisan schedule:run >> /dev/null 2>&1
  ```
- Windows (Task Scheduler): buat tugas yang berjalan **setiap 1 menit** dengan perintah `php D:\broseven-shop\artisan schedule:run`.

Pengaturan: **Pengaturan > Profil Perusahaan > Hapus otomatis pesanan draft setelah (jam)**. Default 24, rentang 0 sampai 720, `0` = nonaktif. Hanya pesanan `DRAFT` yang belum pernah disetujui yang terhapus. Stok tidak berubah karena DRAFT belum mengurangi stok. Setiap penghapusan tercatat di `audit_logs` dengan aksi `order.draft_expired`.

Uji tanpa menghapus: `php artisan orders:purge-drafts --dry-run`.

## Aturan bisnis

**Status pesanan**

DRAFT -> MENUNGGU -> SELESAI
DRAFT -> DIBATALKAN
MENUNGGU -> DIBATALKAN


| Status | Arti |
|---|---|
| DRAFT | Pesanan dibuat customer, belum divalidasi kasir. Stok belum berkurang |
| MENUNGGU | Disetujui kasir, stok sudah berkurang, menunggu pembayaran |
| SELESAI | Pembayaran diterima. Hanya status ini yang dihitung sebagai pendapatan |
| DIBATALKAN | Dibatalkan dengan alasan wajib. Stok dikembalikan bila sudah sempat dikurangi |

**Stok**

- Stok hanya berubah lewat `StockService`, dalam transaksi database dengan penguncian baris, dan selalu mencatat `stock_movements` (`STOCK_IN`, `SALE`, `SALE_CANCELLED`, `RETURN`, `ADJUSTMENT`).
- Perubahan stok langsung pada model ditolak oleh aplikasi. Riwayat stok tidak dapat diubah atau dihapus.
- Persetujuan memeriksa ulang stok. Jika kurang, persetujuan ditolak dan stok terbaru ditampilkan.
- Retur tidak dapat melebihi jumlah yang dibeli, dan tidak dapat diproses dua kali (kunci idempoten).

**Barcode dan kepemilikan**

- QR Code berisi kode acak 24 karakter. Barcode hanya berlaku untuk pesanan DRAFT dan hangus setelah disetujui.
- Pesanan DRAFT terlihat oleh semua kasir. Setelah disetujui, hanya kasir penyetuju yang dapat melihat, menyelesaikan, membatalkan, dan meretur pesanan itu.
- Superadmin dan Admin Master tidak dapat menyetujui atau menyelesaikan pesanan.

**Laporan**

- Pendapatan dihitung dari pesanan SELESAI pada tanggal penyelesaian. Nilai retur ditampilkan terpisah (kotor, retur, bersih).

## Perintah Artisan

| Perintah | Fungsi |
|---|---|
| `orders:purge-drafts [--dry-run] [--hours=N]` | Hapus pesanan DRAFT yang melewati batas waktu |
| `inventory:reconcile [--fix]` | Periksa konsistensi stok, riwayat, pesanan, dan retur. `--fix` hanya menambah movement `ADJUSTMENT` |
| `inventory:stress-approve` | Uji konkurensi persetujuan dengan proses paralel (hanya non-production) |
| `dev:seed-history [--orders=N] [--days=N] [--purge]` | Buat atau hapus riwayat transaksi demo (hanya non-production) |
| `dev:explain` | Jalankan EXPLAIN pada query utama laporan (hanya non-production) |
| `schedule:work` / `schedule:run` | Jalankan penjadwal |

## Pengujian

Tes memakai database MySQL **terpisah** bernama `broseven_shop_test`. Nama database dipaksa lewat `phpunit.xml`, dan tes berhenti sebelum berjalan jika nama tidak berakhiran `_test`, sehingga database development tidak ikut terhapus.

```bash
php tools/create-test-db.php      # membuat database tes (butuh hak CREATE DATABASE)
php artisan test
```

Cakupan: autentikasi, matriks role, CRUD master data dan produk, storefront, alur kasir (barcode, persetujuan, pembatalan), retur, integritas inventori, laporan dan export (termasuk pencegahan rumus di Excel), pengaturan, manajemen user, pencegahan N+1, dan penghapusan otomatis draft.

## Struktur proyek

app/
Console/Commands/ Perintah Artisan
Enums/ Role, OrderStatus, StockMovementType
Http/Controllers/Api/ Controller API (Auth, Store, Cashier, Superadmin, AdminMaster, ...)
Http/Middleware/ EnsureUserHasRole, EnsureUserIsActive
Http/Requests/ Validasi input
Http/Resources/ Bentuk JSON yang aman
Models/ Model Eloquent
Reports/ Pembuat dokumen laporan, exporter PDF dan Excel
Services/ StockService, OrderWorkflowService, ReturnService, DraftCleanupService, ...
database/migrations/ Skema database
resources/
css/ CSS manual
js/ Aplikasi Vue (pages, components, layouts, stores, router, lib)
views/reports/ Template laporan (PDF dan cetak)
routes/api.php Seluruh endpoint API
tests/ Tes otomatis
tools/ Skrip bantu (pembuat database tes)


## Ringkasan API

Semua endpoint ada di bawah `/api` dan memakai session cookie.

| Prefix | Akses |
|---|---|
| `/api/store/*` | Publik (katalog, validasi keranjang, buat pesanan, lacak pesanan) |
| `/api/auth/*` | Login, logout, profil sesi |
| `/api/profile` | Semua role (profil sendiri) |
| `/api/kasir/*` | Kasir |
| `/api/products`, `/api/lookups`, `/api/inventory/*`, `/api/reports/inventory*` | Superadmin dan Admin Master (baca) |
| `/api/products` (tulis), `/api/categories`, `/api/brands`, `/api/product-types`, `/api/units` | Admin Master |
| `/api/users`, `/api/reports/*`, `/api/monitor/*`, `/api/settings/*`, `/api/superadmin/*` | Superadmin |

Hak akses dijaga di backend lewat middleware `role`, bukan hanya dengan menyembunyikan menu.

## Keamanan

- Password di-hash. Aturan password: 8 sampai 72 karakter, wajib huruf dan angka.
- Login dibatasi (5 percobaan per menit per akun dan IP) dan tidak membedakan "user tidak ada" dari "password salah".
- Akun Superadmin utama tidak dapat dihapus, dinonaktifkan, atau diubah rolenya, dan hanya dikelola pemiliknya.
- Harga dan total selalu dihitung server. Nilai dari browser diabaikan.
- Token akses pesanan customer disimpan sebagai hash SHA-256.
- Upload gambar divalidasi (JPG, PNG, WEBP, maksimal 2 MB, tanpa SVG).
- Export Excel menulis teks sebagai sel teks sehingga tidak menjadi rumus.
- Aktivitas penting dicatat di `audit_logs`.

## Checklist production

Daftar ini adalah panduan, belum diverifikasi di lingkungan production Anda.

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` terisi, `APP_URL` memakai `https://`.
- [ ] HTTPS aktif. Pertimbangkan `SESSION_SECURE_COOKIE=true`.
- [ ] Jika di belakang proxy atau load balancer, konfigurasikan *trusted proxies* agar pembatasan laju memakai IP klien yang benar.
- [ ] Password Superadmin kuat. Hapus akun uji dan data demo (`dev:seed-history --purge`).
- [ ] `php artisan migrate --force`, `php artisan storage:link`.
- [ ] `npm run build`, lalu `php artisan config:cache`, `route:cache`, `view:cache`, `event:cache`.
- [ ] Penjadwal Laravel aktif (cron atau Task Scheduler).
- [ ] Izin tulis pada `storage/` dan `bootstrap/cache/`.
- [ ] Backup database terjadwal (contoh `mysqldump`) dan backup `storage/app/public`.
- [ ] Pertimbangkan driver session dan cache yang lebih sesuai (misalnya database atau Redis) bila trafik tinggi.
- [ ] Jalankan `inventory:reconcile` secara berkala dan pantau hasilnya.

## Pemecahan masalah

| Gejala | Penyebab dan solusi |
|---|---|
| Kamera tidak menyala | Gunakan HTTPS atau `localhost`, dan izinkan akses kamera di browser |
| Export gagal atau error `Class ... not found` | Jalankan `composer install`. Pastikan ekstensi `zip`, `xmlreader`, `dom` aktif |
| Logo tidak tampil di PDF | Hanya JPG/PNG maksimal 1,5 MB dan butuh ekstensi `gd` |
| Gambar produk tidak tampil | Jalankan `php artisan storage:link` |
| Pesanan draft tidak terhapus otomatis | Penjadwal belum berjalan, atau batas jam pada pengaturan perusahaan bernilai 0 |
| Tes berhenti dengan pesan "bukan database testing" | `phpunit.xml` tidak memaksa `DB_DATABASE=broseven_shop_test`. Periksa berkas itu |
| Angka "hari ini" meleset beberapa jam | Atur zona waktu di `config/app.php` |
| Perubahan `.env` tidak terbaca | Jalankan `php artisan config:clear` |

## Lisensi
Copyright BroSeven! Developer
