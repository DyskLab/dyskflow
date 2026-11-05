# Setup DyskFlow

Panduan memasang DyskFlow dari nol. Tidak perlu Node atau build. Composer juga tidak perlu: folder `vendor` dipasang dari halaman situs.

## Yang dibutuhkan

- PHP 8.3 atau lebih baru
- Ekstensi PHP: `mbstring`, `openssl`, `fileinfo`, `dom`, `zip`, dan `pdo_mysql` atau `pdo_sqlite`
- Ekstensi opsional: `gd` untuk mengolah gambar, `curl` untuk mengunduh
- Database MySQL yang masih kosong, atau SQLite tanpa server database
- Akses internet dari server ke GitHub untuk mengunduh `vendor`. Jika tidak ada, lihat [Server tidak bisa mengunduh](#server-tidak-bisa-mengunduh)

## 1. Ambil kode

Pilih salah satu:

- Unduh ZIP dari <https://github.com/FannyDevz/dyskflow> (**Code > Download ZIP**), lalu ekstrak.
- Clone repo:

```bash
git clone https://github.com/FannyDevz/dyskflow.git
```

## 2. Siapkan folder

### Di hosting

1. Unggah semua file ke hosting, tanpa folder `vendor`.
2. Jika bisa, arahkan document root ke folder `public`. Jika tidak bisa, `.htaccess` di folder utama meneruskan permintaan ke `public` (khusus Apache dan LiteSpeed). Untuk Nginx, document root harus ke `public`, dengan konfigurasi Laravel standar.
3. Pastikan folder berikut bisa ditulis oleh PHP:
   - folder utama aplikasi, untuk membuat `.env` dan `vendor`
   - `storage/`
   - `bootstrap/cache/`
   - `public/uploads/`
   - `database/`, jika memakai SQLite

### Di cPanel, dalam `public_html`

Taruh semua file langsung di `public_html`, sehingga `public_html/.htaccess` dan `public_html/public/` ada.

1. Di **File Manager > Settings**, aktifkan **Show Hidden Files**, lalu pastikan `.htaccess` ikut terunggah.
2. Di **MultiPHP Manager**, pilih PHP 8.3 atau lebih baru. cPanel bisa menambahkan blok `php8x` ke `.htaccess`; biarkan blok itu.
3. `.htaccess` bawaan sudah meneruskan semua permintaan ke `public`. Setelah SSL terpasang, tambahkan aturan paksa HTTPS, sehingga isinya menjadi seperti ini. `public/.htaccess` tidak perlu diubah.

```apache
Options -Indexes

<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    RewriteRule ^public/ - [L]
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>

<IfModule !mod_rewrite.c>
    Require all denied
</IfModule>
```

4. Saat wizard meminta URL situs, isi `https://domainmu.com`, tanpa `/public`.
5. Setelah terpasang, buka `https://domainmu.com/.env`. Hasilnya harus 404, bukan isi file.

Untuk addon domain atau subdomain, lebih aman arahkan document root ke folder `public`. `.htaccess` di folder utama tidak lagi dipakai, jadi paksa HTTPS lewat **Force HTTPS Redirect** di menu **Domains**.

Jika muncul masalah:

- **Error 500**: hapus baris `Options -Indexes`, karena sebagian hosting melarangnya di `.htaccess`.
- **`ERR_TOO_MANY_REDIRECTS`**: biasanya terjadi di balik proxy seperti Cloudflare mode Flexible. Hapus dua baris paksa HTTPS, atau ubah mode SSL Cloudflare ke Full.
- **Error 403**: kemungkinan `mod_rewrite` tidak aktif. Minta hosting mengaktifkannya.

### Di komputer sendiri

Jalankan dari folder utama aplikasi:

```bash
php -S 127.0.0.1:8000 -t public
```

Situs lalu terbuka di <http://127.0.0.1:8000>. Dengan Laravel Herd, cukup taruh foldernya di direktori Herd.

## 3. Pasang vendor

Buka situsnya. Karena `vendor` belum ada, muncul halaman **Pasang dependensi**. Tekan **Pasang sekarang** dan tunggu sampai selesai. File `.env` dengan `APP_KEY` baru sudah dibuat otomatis.

## 4. Ikuti wizard

Wizard terbuka setelah `vendor` terpasang. Jika tidak, buka `/install`.

1. **Periksa persyaratan.** Semua baris harus lolos. Perbaiki yang gagal, lalu muat ulang.
2. **Database.** Pilih SQLite, atau MySQL dengan host, port, nama database, pengguna, dan sandi. Database harus kosong.
3. **Situs dan administrator.** Isi judul, URL, zona waktu, bahasa, serta nama, email, dan sandi administrator (minimal 8 karakter).

Selesai. Beranda ada di `/`, dan admin ada di `/admin`.

## 5. Setelah terpasang

- Aktifkan verifikasi dua langkah (TOTP) dari peringatan merah di admin, lalu simpan kode pemulihannya.
- Buka **Pengaturan > Kesehatan situs** dan selesaikan yang ditandai.
- Isi **Pengaturan > Kontak** agar halaman `/kontak` lengkap, dan **Pengaturan > Email** agar email bisa terkirim.
- Buka **Pengaturan > Panduan privasi** untuk membuat draf Kebijakan Privasi.
- Jika ingin data contoh dan punya terminal, jalankan perintah berikut. Perintah ini aman dijalankan ulang.

```bash
php artisan db:seed
```

## Jika ada masalah

### Server tidak bisa mengunduh

Unduh `vendor.zip` dari [halaman rilis](https://github.com/FannyDevz/dyskflow/releases/latest), taruh di folder utama aplikasi, muat ulang halaman, lalu tekan **Pasang sekarang**. File itu terhapus sendiri setelah diekstrak.

### Pesan lainnya

- **`Ekstensi PHP zip tidak aktif`**: aktifkan `zip` di panel hosting atau `php.ini`.
- **`Cannot write the vendor folder`**: folder utama aplikasi belum bisa ditulis PHP.
- **`This database already has CMS tables`**: pakai database yang kosong.
- **`Cannot connect to the database`**: periksa host, port, nama database, pengguna, dan sandi. Pengguna harus punya izin membuat tabel.
- **Baris `.env` gagal di Periksa persyaratan**: folder utama belum bisa ditulis, sehingga `.env` tidak terbuat. Perbaiki izinnya, lalu muat ulang.
- **Tampilan tanpa gaya atau tautan salah**: URL situs di wizard tidak sama dengan alamat yang dibuka. Perbaiki `APP_URL` di `.env` dan URL situs di **Pengaturan > Umum**.

### Mengulang instalasi

**Peringatan:** langkah ini membuang semua data situs tersebut. Lakukan hanya pada instalasi percobaan.

1. Hapus `.env` dan `storage/app/.installed`.
2. Kosongkan database MySQL, atau hapus `database/database.sqlite`.
3. Buka situsnya lagi.

## Memperbarui

Buka **Pengaturan > Pembaruan > Periksa pembaruan > Perbarui sekarang**. Backup database dibuat lebih dulu. `.env`, `storage`, `landing`, `plugins`, `public/uploads`, dan `cms.json` tidak ditimpa.
