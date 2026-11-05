# DyskFlow

CMS berbasis Laravel, mirip WordPress, tetapi landing page dikoding terpisah (manual atau dengan AI). Tidak perlu build: tanpa Node, tanpa Vite, dan hosting tidak perlu Composer.

**Butuh:** PHP 8.3+, ekstensi `pdo_mysql` atau `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `dom`, `zip`. Opsional: `gd`, `curl`.

## Fitur

- Install wizard, termasuk pemasangan `vendor` tanpa terminal
- Artikel dan halaman dengan SEO: meta, canonical, Open Graph, JSON-LD, pratinjau hasil pencarian, daftar periksa
- Kategori, tag, komentar dengan moderasi, media
- Konten dua bahasa (Indonesia dan Inggris): artikel, halaman, kategori, dan tag punya URL per bahasa seperti `/en/blog/slug`, dengan `hreflang` dan sitemap. Bahasa default tetap memakai URL tanpa awalan. Halaman tanpa terjemahan tidak punya URL di bahasa itu
- `sitemap.xml`, `robots.txt`, RSS per bahasa (`/feed`, `/en/feed`)
- Landing page terpisah di folder `landing/`
- Plugin di folder `plugins/`, dengan hook dan halaman pengaturan otomatis
- Pengguna dan Peran di sidebar
- Pengaturan: Umum, Kontak, Email, SEO, Media, Komentar, Halaman, Plugin, Log Aktivitas, Backup, Impor & Ekspor, Pembaruan
- Pencarian global di header (`⌘K` atau `Ctrl+K`), sidebar bisa diperkecil, breadcrumb di semua halaman admin
- Verifikasi dua langkah (TOTP) dengan kode pemulihan, plus peringatan di admin bagi yang belum mengaktifkannya
- Notifikasi di header untuk komentar baru, versi baru, dan error, dengan halaman daftar sendiri
- Tampilan token shadcn netral dengan warna utama DyskFlow, sebagai CSS biasa, mode gelap, bahasa Indonesia dan Inggris

## Rilis pertama (sekali saja)

Situs mengunduh `vendor` dari GitHub Release, jadi repo publik harus punya rilis lebih dulu.

1. Isi `"repo"` di `cms.json`, contoh `FannyDevz/dyskflow`.
2. Push ke GitHub, lalu buat tag yang sama dengan isi file `VERSION`:

```bash
git tag v0.1.0
```

```bash
git push origin main --tags
```

GitHub Actions membuat rilis berisi `vendor.zip` secara otomatis ([release.yml](.github/workflows/release.yml)).

## Pasang di hosting

1. Unggah semua file ke hosting, tanpa folder `vendor`.
2. Jika bisa, arahkan document root ke folder `public`. Jika tidak bisa, `.htaccess` di folder utama meneruskan permintaan ke `public` (khusus Apache/LiteSpeed).
3. Buka situsnya, tekan **Pasang sekarang**, lalu ikuti wizard.

Jika server tidak bisa mengunduh dari GitHub, unduh `vendor.zip` dari halaman rilis dan taruh di folder utama aplikasi, lalu muat ulang halaman.

Pastikan `storage/`, `bootstrap/cache/`, `public/uploads/`, dan folder utama bisa ditulis oleh PHP. Untuk Nginx, arahkan document root ke `public` dan pakai konfigurasi Laravel standar.

## Merilis pembaruan

1. Ubah `VERSION`, misalnya `0.2.0`.
2. Commit, buat tag `v0.2.0`, lalu push tag-nya.
3. Di situs, notifikasi muncul dalam 12 jam setelah admin membuka halaman admin. Lalu buka **Pengaturan > Pembaruan > Periksa pembaruan > Perbarui sekarang**.

Yang terjadi saat memperbarui:

- Backup database dibuat lebih dulu.
- Kode diambil dari rilis terbaru.
- `vendor` hanya diganti jika `composer.lock` berubah.
- Migrasi database dijalankan.
- `.env`, `storage`, `landing`, `plugins`, `public/uploads`, dan `cms.json` tidak pernah ditimpa.

## Data contoh

Setelah wizard selesai, isi situs dengan kategori, tag, artikel, halaman, komentar, dan media contoh, termasuk landing page DyskFlow di `/tentang-dyskflow`. Aman dijalankan ulang:

```bash
php artisan db:seed
```

## Terkunci dari TOTP

Pakai kode pemulihan saat login. Admin lain bisa memakai **Pengguna > Reset TOTP**. Jika tidak ada yang bisa login:

```bash
php artisan cms:totp-reset email@contoh.com
```

## Dokumentasi lain

- [Landing page](landing/README.md)
- [Plugin](plugins/README.md)

## Pengembangan lokal

```bash
composer install
```

```bash
php artisan serve
```

```bash
php artisan test
```

## Aturan UI

- Tabel selalu memakai `App\Support\DataTable`, sehingga selalu punya checkbox, pagination, sorting, dan filter.
- Form memakai right sheet. Konfirmasi seperti hapus memakai modal.
- Semua teks ditulis dengan `__('English text')`, dan terjemahannya ada di `lang/id.json`.
