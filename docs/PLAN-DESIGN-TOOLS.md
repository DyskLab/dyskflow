# Rencana: File Manager, Code Editor & Web Builder

Status: **Fase 1 dan 2 selesai (10 Nov 2026)**, plus konfirmasi password/TOTP, kill switch, rate limit, dan backup `theme/` + `landing/` dari Fase 4. Builder (Fase 3) mengikuti [PLAN-WEB-BUILDER.md](PLAN-WEB-BUILDER.md). Dokumentasi pengguna: [DESIGN-TOOLS.md](DESIGN-TOOLS.md).

## 1. Tujuan

Menambahkan alat desain ke dalam admin DyskFlow:

1. File Manager untuk melihat, membuat, mengubah nama, menghapus, dan mengunggah file frontend.
2. Code Editor untuk HTML/Blade, CSS, dan JavaScript.
3. Theme Editor untuk mengubah tampilan tema bawaan tanpa kehilangan perubahan saat update.
4. Web Builder untuk menyusun landing page dari blok, lalu menghasilkan halaman di `landing/<slug>/`.

## 2. Kendala yang menentukan desain

| Kendala | Akibat pada desain |
| --- | --- |
| Update menimpa `app/`, `resources/`, `public/`, `routes/`, `config/`, `lang/` | File yang diedit tidak boleh berada di folder core. Perlu folder override yang dilindungi update. |
| Tanpa build step, tanpa CDN di admin | Editor harus file statis lokal. Tidak boleh ada Node, Vite, atau CDN. |
| Sering di shared hosting | Tidak boleh bergantung pada `exec`, queue, cron, atau ekstensi baru. Semua harus punya fallback. |
| Mengedit PHP/Blade = menjalankan kode di server | Fitur ini setara akses server. Wajib capability khusus, audit log, dan peringatan. |
| Landing page adalah kode, bukan konten | Web Builder harus menulis ke `landing/`, bukan tabel konten. |
| Backup saat ini hanya database + `public/uploads` | Perlu memperluas backup agar perubahan tema/landing tidak hilang. |

**Kesimpulan:** jangan membuat editor yang bisa mengedit seluruh filesystem, dan jangan mengedit file core secara langsung.

## 3. Keputusan arsitektur yang direkomendasikan

### 3.1 Zona yang boleh diedit

| Folder | Isi | Status |
| --- | --- | --- |
| `theme/` | Override tema: view `site/*`, CSS, JS, aset | Baru, dilindungi update |
| `landing/<slug>/` | Halaman landing: Blade/HTML, `landing.json`, aset | Sudah ada, dilindungi update |
| `plugins/<slug>/` | Kode plugin | Sudah ada, dilindungi update |

Yang **tidak** bisa diedit dari admin: `app/`, `resources/`, `config/`, `routes/`, `database/`, `bootstrap/`, `vendor/`, `.env`, `public/` (kecuali lewat Media).

### 3.2 Theme override

Tambahkan folder `theme/` di root:

```
theme/
  views/site/
    layout.blade.php          # opsional, menimpa resources/views/site/layout.blade.php
    partials/header.blade.php
    ...
  assets/
    site.css                  # menimpa public/css/site.css
    site.js                   # menimpa public/js/site.js
    img/...
```

Cara kerja:

- Di `AppServiceProvider::boot()`, panggil `View::prependLocation(base_path('theme/views'))` bila folder ada. View `site.layout` otomatis memakai versi di `theme/` bila file itu ada; kalau tidak, jatuh ke core.
- Tambahkan helper `theme_asset('css/site.css')`. Helper mengembalikan URL file di `theme/assets/` bila ada, atau `asset()` biasa bila tidak.
- Route `_theme/{path}` menyajikan aset tema dengan allowlist ekstensi dan cache satu minggu, mengikuti pola `Landing::assetUrl()`.
- Core `site/layout.blade.php` diubah sekali untuk memakai `theme_asset()` pada CSS/JS. Halaman landing boleh memakainya juga; kalau tidak, landing tetap memakai aset core.
- Tombol **Reset** cukup menghapus file override; tampilan kembali ke core.
- Tambahkan `theme` ke `config('cms.update_protected')`.

Alternatif yang ditolak: menulis langsung ke `resources/views/site/` atau `public/css/site.css`, karena akan hilang pada update berikutnya.

### 3.3 Satu editor, tiga mode

HTML/Blade, CSS, dan JavaScript bukan tiga editor terpisah. Itu tiga mode dari editor yang sama:

| Mode | Ekstensi | Highlight | Capability |
| --- | --- | --- | --- |
| Blade / HTML | `.blade.php`, `.html` | HTML + PHP + overlay direktif Blade | `code.manage` |
| CSS | `.css` | CSS | `appearance.manage` |
| JavaScript | `.js` | JavaScript | `appearance.manage` |
| JSON | `.json` | JSON | `appearance.manage` |
| Teks lain | `.txt`, `.md`, `.xml`, `.svg` | plain | `appearance.manage` |
| PHP | `.php` | PHP | `code.manage` |

Editor: **CodeMirror 5** yang di-vendor lokal di `public/vendor/codemirror/` (MIT). Tanpa build, tanpa CDN. Mode: `xml`, `css`, `javascript`, `htmlmixed`, `php`, `clike`, `markdown`, plus addon search, bracket matching, closebrackets, dan active-line. Ukuran ±250–350 KB, hanya dimuat di halaman editor.

Fitur editor:

- Nomor baris, highlight, bracket matching, auto-indent.
- Cari/ganti (`Ctrl+F`), lompat ke baris (`Ctrl+G`).
- Simpan `Ctrl+S`, indikator file berubah (dirty guard).
- Pilihan tema editor mengikuti dark mode admin.
- Status bar: path, bahasa, baris/kolom, ukuran file.
- Tombol **View** membuka halaman publik terkait di tab baru.
- Konflik penyimpanan: kirim `mtime` + hash; bila file berubah di disk, tolak dengan 409 dan tawarkan **Reload** atau **Overwrite**.

### 3.4 Web Builder

> Rencana yang lebih rinci ada di [PLAN-WEB-BUILDER.md](PLAN-WEB-BUILDER.md): sumber `page.json` (bukan `builder.json`), section/kolom responsif, dan widget kustom sebagai jembatan ke kode. Bila disetujui, dokumen itu menggantikan bagian ini.

**Pendekatan yang direkomendasikan: builder sebagai penghasil halaman di `landing/`.**

- Sumber data: `landing/<slug>/builder.json` (draft dan hasil publish).
- `index.blade.php` yang dihasilkan tipis:
  ```blade
  @include('builder.page', ['spec' => $spec])
  ```
- `Landing::render()` membaca `builder.json` dan mengirim `$spec` ke view bila ada.
- Bentuk halaman tetap kode di `landing/`, bukan konten di database. Ini sesuai aturan proyek.
- Builder tidak menyentuh halaman artikel/page di admin.

Alur draft/publish:

1. **Draft** disimpan ke `landing/<slug>/builder.json` (tidak tampil publik).
2. **Preview** merender draft lewat route admin, di dalam iframe. Tidak ada cache publik.
3. **Publish** menulis `landing.json` (`title`, `description`, `public`, `menu`) dan memastikan `index.blade.php` ada.
4. **Unpublish** mengubah `public` menjadi `false`; halaman 404 tetapi draft tetap ada.
5. **Delete** menghapus folder setelah konfirmasi dan backup revisi.

Blok MVP:

| Blok | Isi |
| --- | --- |
| Hero | eyebrow, judul, teks, 1–2 tombol, gambar/background, perataan |
| Rich text | teks dari editor yang sudah ada, disanitasi `HtmlSanitizer` |
| Image + text | gambar kiri/kanan, judul, teks, tombol |
| Features | 3–6 item: ikon/gambar, judul, teks |
| Gallery | 2–12 gambar + caption |
| Latest posts | jumlah, kategori, memakai `cms_posts()` |
| CTA band | judul, teks, tombol |
| FAQ | daftar tanya-jawab memakai `<details>` tanpa JS |
| Contact | memakai setting kontak, peta, sosial |
| Spacer / divider | tinggi dan garis |
| Custom HTML | hanya untuk `code.manage`, disanitasi, dengan peringatan |

Fitur builder:

- Daftar blok di kiri: tambah, duplikat, hapus, geser atas/bawah, dan drag-and-drop dengan fallback tombol (aksesibilitas keyboard).
- Preview tengah: desktop / tablet / mobile, memakai iframe.
- Panel kanan: field sesuai tipe blok, skema field dibuat generik seperti `SettingsSchema`.
- Tab bahasa ID/EN untuk setiap teks. Teks disimpan per locale di JSON.
- Media picker memakai endpoint `admin.media.picker` yang sudah ada.
- `Save draft`, `Preview`, `Publish`, `View`, `Edit code`, `Duplicate page`, `Delete`.
- Kalau `index.blade.php` diedit manual (hash berubah), builder menampilkan peringatan dan menawarkan **Duplicate** atau **Take over**. Builder tidak diam-diam menimpa kode manual.
- Halaman baru memakai slug valid `^[a-z0-9][a-z0-9_-]*$`, bukan `reserved_slugs`, dan tidak menimpa halaman admin dengan slug sama.
- Bisa dijadikan homepage lewat **Settings > Pages** yang sudah ada; builder hanya menautkan ke sana.

Yang **tidak** dilakukan di MVP: drag bebas/absolute positioning, custom CSS per blok, dan halaman builder yang disimpan di database. Ini menjaga hasil tetap responsif dan tidak menjadi page builder yang rapuh.

## 4. Keamanan

Ini bagian terpenting. Fitur ini sengaja memberi akses menulis kode yang dijalankan server.

### 4.1 Capability

Tambahkan dua capability di `config/cms.php`:

| Capability | Boleh |
| --- | --- |
| `appearance.manage` | File Manager, CSS/JS/HTML/JSON, Theme Editor, Web Builder |
| `code.manage` | Semua di atas + `.blade.php` dan `.php` (setara akses server) |

- `administrator` (`*`) mendapat keduanya.
- `editor` dan `author` tidak mendapatkannya secara default.
- Role form otomatis menampilkan capability baru karena daftarnya dari config.

### 4.2 Validasi path dan file

- Root yang diizinkan hanya `theme`, `landing`, `plugins` (daftar di config).
- Resolusi dengan `realpath()`, lalu pastikan path hasilnya benar-benar di dalam root. Tolak `..`, null byte, path absolut, dan symlink.
- Tolak dotfile dan nama sensitif: `.env`, `.htaccess`, `.git`, `vendor`, `storage`.
- Allowlist ekstensi per root; `.blade.php` dikenali sebelum `.php`.
- Ukuran edit maksimum 512 KB; upload mengikuti `media_max_mb`.
- Upload hanya untuk aset (gambar, font, CSS, JS), bukan PHP/HTML. SVG diperlakukan sebagai file berisiko dan hanya untuk `code.manage`.
- Tulis atomik: tulis ke file sementara di folder yang sama, lalu `rename()`.
- Setelah menyimpan Blade/PHP: `opcache_invalidate()` bila ada, dan `Artisan::call('view:clear')`.
- Untuk `.php` dan `.blade.php`, jalankan `php -l` bila `exec` tersedia. Bila tidak tersedia, tampilkan peringatan bahwa sintaks tidak diverifikasi, bukan mengklaim aman.
- Untuk JSON, validasi `json_decode` dan tolak bila invalid.

### 4.3 Audit dan pemulihan

- Setiap aksi (`file.saved`, `file.created`, `file.deleted`, `file.uploaded`, `theme.reset`, `builder.published`) dicatat lewat `activity()`.
- Tabel `file_revisions`: `id`, `path`, `content`, `size`, `user_id`, `created_at`. Simpan konten **sebelum** ditimpa. Simpan maksimal 20 revisi per file, hapus yang lebih lama. Tambahkan ke `backup_tables`.
- UI revisi: daftar waktu + user, lihat isi, **Restore**. Restore juga mencatat revisi baru.
- Sebelum update CMS, `UpdateService` sudah membuat backup database. Perluas backup penuh agar menyertakan `theme/` dan `landing/` (kecuali file media besar seperti `mp4`/`webm` bila perlu batas ukuran).
- Peringatan di UI: "Mengedit kode sama dengan memberi akses ke server. Berikan hanya kepada orang yang dipercaya."

### 4.4 Konfirmasi tindakan sensitif

- Untuk membuka editor `.blade.php`/`.php`, minta konfirmasi ulang: kode TOTP bila aktif, atau password bila TOTP tidak aktif. Session flag berlaku 15 menit.
- Hapus file, hapus folder, publish, dan restore revisi memakai modal `data-confirm` yang sudah ada.
- Rate limit pada endpoint simpan dan upload.
- Kill switch di `.env`: `CMS_CODE_EDITOR=false` menonaktifkan seluruh route editor kode (berguna saat insiden).

## 5. Struktur kode yang diusulkan

### File baru

```
app/Support/SafeFiles.php              # validasi path, baca/tulis, allowlist, revisi
app/Support/Theme.php                  # scaffold override, daftar file tema, reset
app/Support/Builder.php                # skema blok, validasi spec, draft/publish
app/Support/FileRevision.php?          # model, atau langsung DB di SafeFiles
app/Http/Controllers/Admin/FileController.php
app/Http/Controllers/Admin/ThemeController.php
app/Http/Controllers/Admin/BuilderController.php
app/Http/Middleware/ConfirmCodeAccess.php
resources/views/admin/files.blade.php
resources/views/admin/file-editor.blade.php
resources/views/admin/theme.blade.php
resources/views/admin/builder.blade.php
resources/views/builder/page.blade.php
resources/views/builder/blocks/*.blade.php
public/js/builder.js
public/js/editor.js
public/vendor/codemirror/...
database/migrations/xxxx_create_file_revisions_table.php
theme/                                  # dibuat saat override pertama
```

### File yang diubah

```
config/cms.php                          # capability, update_protected, editable_roots
app/Support/AdminMenu.php               # grup "Design"
app/Support/Landing.php                 # kirim $spec bila builder.json ada
app/Providers/AppServiceProvider.php    # prependLocation theme/views
resources/views/site/layout.blade.php   # theme_asset()
app/Support/BackupService.php           # sertakan theme/ dan landing/
lang/id.json                            # teks UI baru
routes/web.php                          # route admin design
docs/AI-GUIDE.md                        # catatan theme/ dan builder
```

### Rute admin

```
GET  admin/design/files
GET  admin/design/files/edit?path=...
POST admin/design/files/save
POST admin/design/files/create
POST admin/design/files/rename
POST admin/design/files/delete
POST admin/design/files/upload
GET  admin/design/files/revisions?path=...
POST admin/design/files/restore

GET  admin/design/theme
POST admin/design/theme/override
POST admin/design/theme/reset

GET  admin/design/builder
GET  admin/design/builder/{slug}
POST admin/design/builder/{slug}/draft
POST admin/design/builder/{slug}/publish
POST admin/design/builder/{slug}/unpublish
POST admin/design/builder/{slug}/delete
GET  admin/design/builder/{slug}/preview
```

Semua route memakai `web`, `auth`, dan `can:appearance.manage` (plus `can:code.manage` pada aksi kode). Breadcrumb otomatis dari nama route `admin.design.*`.

## 6. UI

- Sidebar grup **Design**:
  - **Files** (`folder`) — file manager + editor.
  - **Theme** (`code`) — daftar file tema core dengan status *Default* / *Overridden*, tombol Edit dan Reset.
  - **Builder** (`landing`) — daftar landing page + tombol New page.
- File manager memakai tree view, bukan tabel database. Aturan `DataTable` tetap dipakai untuk tabel data; daftar file adalah tree.
- Editor memakai halaman penuh (bukan right sheet) karena butuh ruang. Form kecil (rename, new file) tetap memakai right sheet.
- Semua teks lewat `__()`, terjemahan di `lang/id.json`.
- Warna dan komponen memakai token/class yang sudah ada (`btn`, `card`, `badge`, `--primary`).

## 7. Rencana bertahap

### Fase 0 — Keputusan dan desain (1–2 hari)

- Setujui cakupan folder, model permission, dan bentuk builder.
- Buat sketsa UI file manager, editor, dan builder.
- Tetapkan format `builder.json` dan skema blok.
- **Output:** dokumen ini disetujui.

### Fase 1 — Fondasi file dan tema (4–6 hari)

- `SafeFiles`, allowlist, validasi path, tulis atomik, revisi.
- `theme/` override, `theme_asset()`, `update_protected`.
- Migrasi `file_revisions`, capability baru, menu Design.
- Test keamanan path dan update compatibility.

### Fase 2 — File Manager + Code Editor (5–7 hari)

- Tree view, create/rename/delete/upload, breadcrumb.
- CodeMirror lokal, mode Blade/CSS/JS/JSON, search, Ctrl+S, dirty guard, konflik.
- Revisi dan restore.
- `php -l`, `view:clear`, audit log.
- Test fitur dan manual di dark mode + mobile.

### Fase 3 — Web Builder MVP (8–12 hari)

- Skema blok generik + 9–10 blok.
- Draft/publish/preview, iframe responsive.
- Media picker, tab bahasa, duplicate/delete.
- Halaman contoh dan dokumentasi.
- Test publish, validasi spec, halaman publik.

### Fase 4 — Pengerasan dan rilis (4–6 hari)

- Konfirmasi TOTP/password, kill switch, rate limit.
- Backup penuh menyertakan `theme/` dan `landing/`.
- Update `docs/AI-GUIDE.md` dan `docs/DESIGN-TOOLS.md`.
- Uji upgrade dari rilis sebelumnya, uji restore, uji tanpa `exec`.
- Update `VERSION` dan catatan rilis.

Total perkiraan: **4–6 minggu** untuk satu developer. MVP yang hanya File Manager + Editor (tanpa Builder) sekitar **2 minggu**.

## 8. Rencana pengujian

### Feature test

- `FileManagerTest`
  - tamu/role tanpa capability ditolak (403).
  - `../`, path absolut, null byte, symlink ditolak.
  - ekstensi terlarang ditolak.
  - simpan CSS/JS/Blade berhasil dan tercatat di activity log.
  - revisi dibuat sebelum overwrite dan bisa direstore.
  - konflik `mtime`/hash menghasilkan 409.
  - `php -l` gagal menolak simpan.
- `ThemeOverrideTest`
  - view di `theme/views` menang atas core.
  - `theme_asset()` memilih aset tema bila ada, fallback bila tidak.
  - reset menghapus override.
  - `theme` ada di `update_protected`.
- `BuilderTest`
  - draft tidak tampil publik.
  - publish menulis `landing.json` dan `index.blade.php`.
  - slug invalid/reserved ditolak.
  - spec invalid ditolak dengan pesan jelas.
  - preview hanya untuk user dengan capability.
  - teks ID/EN tampil di URL yang benar.
- `BackupTest`
  - backup penuh menyertakan `theme/` dan `landing/`.
  - restore menolak path berbahaya.

### Manual

- Editor di layar kecil, dark mode, keyboard only.
- Simpan file saat dua tab terbuka.
- Update CMS setelah mengedit tema: perubahan tetap ada.
- Restore backup: tema dan landing kembali.
- Shared hosting tanpa `exec`: editor tetap bisa menyimpan dengan peringatan.

## 9. Risiko dan mitigasi

| Risiko | Tingkat | Mitigasi |
| --- | --- | --- |
| RCE lewat editor | Tinggi | Capability terpisah, TOTP, audit, revisi, kill switch, peringatan |
| Perubahan hilang saat update | Tinggi | Hanya `theme/landing/plugins`, `theme` masuk `update_protected` |
| File rusak karena simpan setengah jalan | Sedang | Tulis atomik + validasi + revisi |
| Konflik dua admin | Sedang | Cek `mtime`/hash, 409, reload/overwrite |
| Builder menghasilkan halaman tidak responsif | Sedang | Blok section-based, preview 3 ukuran, tanpa absolute positioning |
| Update mengubah blok builder | Sedang | Blok adalah bagian core; dokumentasikan; landing tetap bisa diedit manual |
| Backup tidak mencakup file | Sedang | Perluas backup penuh; tampilkan peringatan |
| CodeMirror menambah ukuran | Rendah | Hanya dimuat di halaman editor, file lokal, lisensi disertakan |
| Shared hosting membatasi tulis file | Sedang | Cek permission saat membuka File Manager, pesan error yang jelas |

## 10. Keputusan yang disetujui

| Keputusan | Pilihan |
| --- | --- |
| Cakupan folder | `theme/`, `landing/`, `plugins/`. Core `resources/views/` tidak bisa diedit. |
| Permission | Dua level: `appearance.manage` dan `code.manage`. Administrator mendapat keduanya. |
| Builder | Runtime renderer dari `builder.json`. |
| Alur simpan | Draft → preview → publish. |
| Backup | Backup penuh menyertakan `theme/` dan `landing/`. |
| Editor | CodeMirror 5 yang di-vendor lokal, tanpa CDN dan tanpa build. |

## 11. Langkah berikutnya (Fase 1)

1. Tambahkan `theme` ke `config('cms.update_protected')` dan `editable_roots`.
2. Buat `App\Support\SafeFiles` + test path traversal, symlink, allowlist, dan tulis atomik.
3. Buat migrasi `file_revisions` dan masukkan ke `backup_tables`.
4. Buat `theme/` override: `View::prependLocation()`, `theme_asset()`, route `_theme/{path}`.
5. Tambahkan capability `appearance.manage` dan `code.manage` di `config/cms.php`.
6. Tambahkan grup **Design** di `AdminMenu` (Files, Theme, Builder) dengan route kosong sebagai kerangka.
7. Jalankan `php artisan test` sebelum lanjut ke Fase 2.
