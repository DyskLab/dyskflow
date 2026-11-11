# Rencana: Web Builder untuk Landing Page

Status: **B1 sampai B5 selesai (10 Nov 2026). Pada 11 Nov 2026 builder pindah ke Halaman (bagian 0), sehingga prinsip "tanpa database" di bawah tidak berlaku lagi.** B4 dan B5: widget konten `faq` (dengan data terstruktur FAQPage opsional), `testimonials`, `pricing`, `stats`, dan `contact` (membaca Pengaturan › Kontak); tiga template awal di `resources/builder-templates/` (produk, bisnis jasa, acara) yang bisa dipilih saat membuat halaman; uji keamanan endpoint editor dan uji round-trip; cek dark mode dan layar ponsel. Widget kustom, layout `custom`, dan widget `html` sudah masuk sejak B1. Tambahan setelahnya: widget `gallery` dan `map` (peta dimuat setelah tombol ditekan, tanpa cookie pihak ketiga), aturan `code.manage` untuk widget `html` di semua jalur tulis admin (bagian 11), perintah `builder:schema` yang membuat schema dari `widget.json`, upload gambar ke `assets/` halaman dari panel gambar, dan item daftar yang bisa dilipat di panel kanan. Terakhir: geser elemen dengan keyboard (Alt + ↑/↓, termasuk pindah ke kolom lain) dan widget `video` yang menerima file mp4/webm lewat tipe field baru `file`. Drag-and-drop juga sudah diuji lewat pipeline mouse asli Chrome (CDP drag interception). Widget `blade` sengaja tidak dibuat: widget kustom memenuhi kebutuhan yang sama tanpa membuat `page.json` menjadi kode PHP. Cache render juga tidak dibuat karena halaman builder dirender sekitar 3 ms, tidak jauh dari halaman kode. B2: editor visual di **Design › Builder** (navigator, palet widget, panel field dari `widget.json`, pratinjau iframe dengan klik-untuk-pilih, tab bahasa, draf otomatis, publish/buang draf, deteksi perubahan file, buat/duplikat/hapus halaman). B3 juga selesai: nilai per perangkat, pratinjau 3 ukuran, undo/redo, inner section, media picker, dan drag-and-drop (palet ke pratinjau atau navigator, susun ulang di navigator, geser widget langsung di pratinjau). B1: format `page.json` versi 1, renderer, 11 widget inti pertama, `builder:validate`, [BUILDER.md](BUILDER.md), [builder.schema.json](builder.schema.json), dan contoh `landing/builder-example/`. Keputusan terbuka di bagian 16 mengikuti rekomendasi: terjemahan yang kosong dirender kosong, form belum masuk MVP, dan landing lama tetap halaman kode. Dokumen ini merinci bagian 3.4 di [PLAN-DESIGN-TOOLS.md](PLAN-DESIGN-TOOLS.md).

## 0. Perubahan 11 Nov 2026: builder menjadi bagian dari Halaman

Builder kini mengikuti konsep Elementor penuh: halaman builder adalah page biasa (`posts`, type `page`) dengan layout JSON di kolom `builder` dan draf di `builder_draft`. Judul, slug, SEO, status, jadwal terbit, menu, terjemahan, sitemap, dan backup database ikut otomatis. Format JSON, token, widget, renderer, dan editor tetap sama.

- Landing di `landing/<slug>/` kembali khusus halaman kode (Blade/HTML) dan tampil di **Desain › Landing pages**.
- AI membaca dan menulis layout lewat `builder:list`, `builder:export`, `builder:import` (`--publish`, `--create`), dan `builder:validate`.
- Gambar yang diupload dari editor masuk pustaka media (`uploads/`). `assets/`, widget per halaman, layout `custom`, dan `style.css` per halaman dihapus. Class kustom diambil dari CSS tema.
- Revisi layout disimpan di `file_revisions` dengan path `page:<id>/builder.json` (20 terakhir), dan memulihkan revisi memasukkannya ke draf.
- Saat terbit, `content` diisi teks dari layout agar pencarian, feed, dan ringkasan tetap berjalan.
- Migration `add_builder_to_posts` memindahkan `landing/<slug>/page.json` yang lama ke Halaman. Folder lamanya dibiarkan sebagai cadangan.

Bagian 1 sampai 16 di bawah adalah rencana awal dan disimpan sebagai catatan.

## 1. Tujuan

Builder visual seperti Elementor untuk landing page di `landing/<slug>/`, dengan tiga syarat:

1. **Layout nyata**: section, kolom, section di dalam kolom, dan pengaturan per desktop/tablet/mobile.
2. **Bisa diedit lewat kode** oleh AI atau manusia, dan hasilnya tetap bisa dibuka lagi di builder.
3. **Tanpa database**: halaman adalah file di `landing/`, aman dari update, bisa di-diff, dan bisa dibackup.

Di luar cakupan: Pages di admin tetap konten di database. Builder tidak mengubah artikel atau page.

## 2. Prinsip inti

| # | Prinsip | Alasannya |
| --- | --- | --- |
| P1 | **Satu sumber kebenaran: `page.json`.** Builder dan AI sama-sama mengedit file ini. | Blade bebas tidak bisa dibaca balik oleh builder. JSON bisa dibaca, divalidasi, dan di-diff. |
| P2 | **Builder tidak pernah menulis file `.blade.php`.** Ia hanya menulis `page.json` dan `page.draft.json`. | Kode tulisan manusia atau AI tidak akan tertimpa diam-diam. |
| P3 | **Kode masuk lewat "jembatan" yang jelas**: widget kustom, layout kustom, CSS halaman, dan class kustom. | Kode dan visual hidup berdampingan, tidak saling merusak. |
| P4 | **Teks dari JSON selalu di-escape.** HTML hanya lewat widget rich text (`HtmlSanitizer`) atau widget kode (`code.manage`). | Mengedit `page.json` cukup memakai `appearance.manage`, bukan akses server. |
| P5 | **Style dari token, bukan CSS bebas.** Spasi, warna, dan ukuran memakai skala tetap dari `ui.css`. | Hasil tetap konsisten dan responsif, dan AI tidak bisa merusak desain. |
| P6 | **Toleran terhadap kesalahan.** Node yang tidak valid dilewati saat render dan dicatat ke log, tidak membuat halaman 500. Node tak dikenal disimpan apa adanya, tidak dihapus. | AI kadang salah tulis. Halaman publik tidak boleh mati karena itu. |

## 3. Struktur folder

```
landing/promo/
  landing.json              # title, description, public, menu (sudah ada)
  page.json                 # halaman yang tayang (sumber builder)
  page.draft.json           # opsional: perubahan yang belum dipublish
  layout.blade.php          # opsional: layout kustom (kode)
  widgets/                  # opsional: widget kustom milik halaman ini (kode)
    countdown/
      widget.json           # skema field, tampil di panel builder
      view.blade.php        # tampilan
  assets/
    style.css               # opsional: CSS kustom halaman
    hero.webp
```

Widget kustom yang dipakai bersama banyak halaman tinggal di `theme/widgets/<name>/` dengan format yang sama. Plugin juga bisa mendaftarkan widget lewat hook, tanpa mengubah core.

### Jenis halaman

`Landing::all()` sekarang mengenali `index.blade.php` dan `index.html`. Tambahkan `page.json` sebagai entry ketiga:

| Entry | Jenis | Dibuka di |
| --- | --- | --- |
| `index.blade.php` / `index.html` | **Code page** (`dyskflow`, `kontak`, `example`) | Code editor |
| `page.json` | **Builder page** | Builder dan code editor |

Kalau `index.*` dan `page.json` ada bersamaan, `index.*` menang dan builder menampilkan peringatan. Kode yang eksplisit selalu menang. Tidak ada stub `index.blade.php` yang dibuat otomatis, jadi tidak perlu cek hash "apakah stub diedit manual".

## 4. Format `page.json`

```json
{
  "$schema": "../../docs/builder.schema.json",
  "version": 1,
  "layout": "site",
  "settings": { "width": "normal", "background": "default" },
  "sections": [
    {
      "id": "s7f2a",
      "type": "section",
      "style": { "padding": { "base": "xl", "mobile": "lg" }, "background": "muted" },
      "columns": [
        {
          "id": "c19bd",
          "width": { "base": 7, "mobile": 12 },
          "align": "center",
          "widgets": [
            { "id": "w0a11", "type": "heading", "level": 1,
              "text": { "id": "Diskon Akhir Tahun", "en": "Year-end Sale" } },
            { "id": "w0a12", "type": "text",
              "html": { "id": "<p>Hemat hingga 50%.</p>", "en": "<p>Save up to 50%.</p>" } },
            { "id": "w0a13", "type": "button", "variant": "primary",
              "label": { "id": "Belanja", "en": "Shop now" }, "url": "/produk" }
          ]
        },
        {
          "id": "c19be",
          "width": { "base": 5, "mobile": 12 },
          "widgets": [
            { "id": "w0a14", "type": "image", "src": "assets/hero.webp",
              "alt": { "id": "Produk unggulan", "en": "Featured product" }, "ratio": "4:3" }
          ]
        }
      ]
    }
  ]
}
```

Aturan format:

- **Struktur**: `section → columns → widgets`. Satu kolom boleh berisi widget `inner-section` (yang berisi kolom lagi). Kedalaman maksimum 2 section supaya tetap sederhana dan responsif.
- **`id`** pendek dan unik per halaman (5 karakter acak). Builder, AI, preview, dan pesan validasi merujuk node lewat id ini. Kalau AI lupa memberi id, builder membuatkannya saat file dibuka.
- **Teks terjemahan** berupa objek `{ "id": "...", "en": "..." }` dengan kunci dari `Locales::ALL`. Teks yang tidak diterjemahkan dirender kosong, mengikuti `Locales::text()`, supaya satu halaman tidak mencampur dua bahasa. Builder menampilkan status kelengkapan per bahasa.
- **Nilai responsif** boleh berupa nilai tunggal (`"padding": "lg"`) atau objek `{ "base", "tablet", "mobile" }`. Yang tidak diisi mewarisi ukuran di atasnya.
- **Simpan stabil**: builder menulis JSON dengan indentasi 2 spasi, urutan kunci tetap, dan `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`. Hasilnya, diff dari builder dan dari AI sama-sama kecil dan mudah dibaca.
- **`version`** untuk migrasi format. Core memigrasi saat membaca, lalu builder menyimpan dengan versi terbaru.
- **`$schema`** opsional. Editor kode (VS Code dan sejenisnya) dan AI bisa memakainya untuk autocomplete dan validasi.

### Token style

| Properti | Nilai |
| --- | --- |
| `padding`, `gap`, `margin-top/bottom` | `none`, `xs`, `sm`, `md`, `lg`, `xl`, `2xl` |
| `background` | `default`, `muted`, `primary`, `dark`, `image` (dengan `image`, `overlay`) |
| `width` (section) | `narrow`, `normal`, `wide`, `full` |
| `width` (kolom) | 1–12 (grid 12 kolom) |
| `align`, `text-align` | `start`, `center`, `end` |
| `hide` | `{ "mobile": true }` dan sejenisnya |
| `class` | class kustom dari `assets/style.css` halaman (jembatan ke kode) |
| `anchor` | id HTML untuk link `#harga` |

Token diubah menjadi class tetap di `public/css/builder.css`, misalnya `.b-pad-xl`, `.md\:b-col-7`, dan `.b-bg-muted`. Tidak ada `style=""` bebas. Gambar background satu-satunya pengecualian: dikirim lewat CSS variable dengan URL yang sudah divalidasi.

## 5. Widget

### Widget core (MVP)

| Grup | Widget |
| --- | --- |
| Layout | `section`, `inner-section`, `spacer`, `divider` |
| Dasar | `heading`, `text` (rich text), `button`, `button-group`, `image`, `icon-list`, `video` (YouTube/Vimeo/file lokal) |
| Konten | `features` (grid ikon + judul + teks), `gallery`, `testimonials`, `pricing`, `faq` (`<details>`, tanpa JS), `cta`, `stats` |
| Data CMS | `posts` (`cms_posts()`), `contact` (Settings > Contact), `map` |
| Kode | `html` (khusus `code.manage`, disanitasi), `blade` (menunjuk file partial di folder halaman, khusus `code.manage`) |

Form (contoh: form kontak dengan submit) tidak masuk MVP. Form membutuhkan CSRF dan cookie, yang bertentangan dengan aturan `CacheablePublicPages`. Widget `contact` cukup memakai link WhatsApp, email, dan telepon.

### Definisi widget

Setiap widget, baik core maupun kustom, punya bentuk yang sama:

```
resources/views/builder/widgets/heading/
  widget.json
  view.blade.php
```

```json
{
  "name": "heading",
  "label": "Heading",
  "icon": "heading",
  "group": "basic",
  "fields": [
    { "key": "text",  "type": "text",   "label": "Text", "translatable": true, "required": true },
    { "key": "level", "type": "select", "label": "Level", "options": [1, 2, 3, 4], "default": 2 },
    { "key": "size",  "type": "select", "label": "Size", "options": ["sm", "md", "lg", "xl", "2xl"], "responsive": true }
  ]
}
```

Tipe field: `text`, `textarea`, `richtext`, `url`, `image`, `icon`, `select`, `toggle`, `number`, `color-token`, `spacing`, `repeater` (daftar item, misalnya isi FAQ), `category`, dan `code` (khusus `code.manage`).

`view.blade.php` menerima `$w` (nilai field yang sudah divalidasi, teks sudah sesuai bahasa) dan `$node` (id, class, anchor). Semua output memakai `{{ }}`. `{!! !!}` hanya untuk field `richtext` yang sudah lewat `HtmlSanitizer`.

### Inilah jembatan kode dan visual

Alur kerja yang ditargetkan:

1. AI atau developer menulis `landing/promo/widgets/countdown/` (`widget.json` + `view.blade.php`). Butuh `code.manage`.
2. Builder langsung menampilkan **Countdown** di daftar widget, lengkap dengan panel field dari `widget.json`.
3. Manusia tanpa akses kode bisa memasang, menggeser, dan mengisi field-nya secara visual.

Jadi kode tidak "mengunci" halaman. Kode menambah alat baru ke builder.

## 6. Layout halaman

`page.json` memilih `layout`:

| Layout | Isi |
| --- | --- |
| `site` | Header dan footer situs (`site.partials.header` / `footer`), `ui.css`, `site.css`, `builder.css`, `site.js` |
| `blank` | Tanpa header dan footer. Hanya CSS dan JS dasar. Cocok untuk halaman iklan. |
| `custom` | `landing/<slug>/layout.blade.php`, ditulis manual atau oleh AI, dan wajib mencetak `{!! $seo !!}` dan `{!! $builder !!}` |

Layout kustom memberi kebebasan penuh pada kerangka halaman (navigasi khusus, script pihak ketiga), sementara isi di tengahnya tetap diedit lewat builder.

## 7. Alur kerja AI

Yang disediakan untuk AI:

| Alat | Isi |
| --- | --- |
| `docs/BUILDER.md` | Panduan: format `page.json`, aturan id, token, cara membuat widget kustom, contoh lengkap |
| `docs/builder.schema.json` | JSON Schema yang dibuat dari definisi widget (core + kustom) |
| `php artisan builder:validate promo` | Cek `page.json` dan widget kustom. Pesan berisi path node, misalnya `sections[0].columns[1].widgets[2] (w0a14): field "src" is required` |
| `php artisan builder:schema` | Mencetak ulang schema setelah widget kustom ditambah |
| `docs/AI-GUIDE.md` | Diperbarui dengan prompt siap pakai |

Aturan untuk AI (ditulis di `BUILDER.md`):

- Edit `page.draft.json` bila file itu ada. Buat file itu bila Anda ingin perubahan direview dulu di admin. Edit `page.json` hanya bila perubahan memang boleh langsung tayang.
- Jangan ubah `id` node yang sudah ada. Beri id baru untuk node baru.
- Pakai token, bukan CSS. Kalau benar-benar butuh CSS, tulis di `assets/style.css` lalu rujuk lewat `class`.
- Kebutuhan yang tidak ada widget-nya ditulis sebagai widget kustom di `widgets/`, bukan HTML mentah.
- Jalankan `builder:validate` setelah mengedit.

Prompt contoh:

> Tambahkan section harga dengan 3 paket ke `landing/promo/page.draft.json`, mengikuti `docs/BUILDER.md`. Jalankan `php artisan builder:validate promo`.

## 8. Alur kerja di admin

### Draft dan publish

1. Builder menyimpan ke `page.draft.json` (autosave tiap 10 detik dan saat `Ctrl+S`).
2. **Preview** merender draft lewat route admin, tanpa cache publik.
3. **Publish**: validasi → simpan revisi `page.json` lama → rename atomik draft menjadi `page.json`.
4. **Discard draft** menghapus `page.draft.json` (dengan konfirmasi `data-confirm`).
5. **Unpublish** mengubah `"public": false` di `landing.json`.

### Perubahan dari luar (AI mengedit saat builder terbuka)

- Builder menyimpan hash file yang ia baca. Simpan dengan hash lama ditolak 409, memakai `FileConflictException` yang sudah ada.
- Builder memeriksa hash tiap 5 detik saat tab aktif. Bila berubah dan belum ada perubahan lokal, builder memuat ulang otomatis dan menampilkan notifikasi kecil "Diperbarui dari file". Bila ada perubahan lokal, builder menawarkan **Muat versi file** atau **Timpa dengan versi saya**.
- Merge per node (memanfaatkan `id`) dicatat sebagai kemungkinan nanti, bukan MVP.

### Revisi

Setiap publish dan setiap simpan dari code editor masuk ke `file_revisions` lewat `SafeFiles`. Draft tidak membuat revisi, karena autosave akan memenuhi tabel.

## 9. UI builder

```
┌──────────────────────────────────────────────────────────────────────┐
│ ← Promo  [Draft]   🖥 💻 📱   ID | EN   ↶ ↷   Code  Preview  Publish │
├──────────────┬───────────────────────────────────┬───────────────────┤
│ Widgets      │                                   │ Heading           │
│ ▸ Layout     │      iframe preview (draft)       │ ───────────────── │
│ ▸ Basic      │   klik elemen = pilih node        │ Content | Style   │
│ ▸ Content    │   garis biru + toolbar kecil      │ | Advanced        │
│ ▸ Custom     │   (geser, duplikat, hapus)        │                   │
│ ───────────  │                                   │ Text   [ID][EN]   │
│ Navigator    │                                   │ Level  [H1 ▾]     │
│ ▾ Section    │                                   │ Size   🖥 xl 📱 lg │
│   ▾ Column   │                                   │                   │
│     Heading  │                                   │                   │
└──────────────┴───────────────────────────────────┴───────────────────┘
```

- Halaman penuh, bukan right sheet, sama seperti code editor.
- **Navigator** (tree) di kiri: drag-and-drop, dengan tombol naik/turun dan keyboard sebagai alternatif.
- **Preview** di iframe yang merender HTML asli dari server. Setiap node punya `data-b-id`. Klik di iframe memilih node lewat `postMessage`. Perubahan field merender ulang hanya section itu lewat endpoint partial, jadi preview selalu sama dengan halaman publik.
- **Panel kanan** dibentuk dari `widget.json`, dengan tab Content / Style / Advanced. Field responsif menampilkan ikon perangkat.
- **Undo/redo** berupa snapshot JSON (50 langkah).
- Tombol **Code** membuka `page.draft.json` (atau `page.json`) di code editor. Kembali ke builder akan memuat ulang file itu.
- Media picker memakai `admin.media.picker`. Gambar bisa dari Media (URL `/uploads/...`) atau dari `assets/` halaman.
- JS biasa di `public/js/builder/` (ES modules native, tanpa build). Drag-and-drop memakai HTML5 DnD bawaan. Tanpa library.

## 10. Rendering

```
Landing::render($slug)
  └─ page.json ada (dan tidak ada index.*)?
       └─ Builder::load()  → baca, migrasi versi, validasi longgar
       └─ Builder::render() → per node: cari widget, normalisasi field,
                              pilih teks sesuai app()->getLocale(),
                              render view widget di dalam try/catch
       └─ bungkus dengan layout site | blank | custom
```

- Node yang gagal dirender dilewati di halaman publik dan dicatat ke log, sehingga muncul sebagai notifikasi admin lewat `Notifier`. Di preview admin, node itu tampil sebagai kotak merah berisi pesannya.
- Hasil parse `page.json` di-cache per `mtime` file, supaya tidak memvalidasi ulang di setiap request.
- `landing_asset()` tetap bekerja untuk `assets/` di halaman builder.

## 11. Keamanan

| Aksi | Capability |
| --- | --- |
| Membuka builder, mengedit `page.json` / draft, upload gambar ke `assets/`, mengedit `assets/style.css` | `appearance.manage` |
| Membuat atau mengedit `widgets/*`, `layout.blade.php`, widget `html` / `blade` | `code.manage` |

- **Saat menyimpan `page.json`**, server membandingkan dengan versi sebelumnya. Bila node `html` atau `blade` ditambah atau diubah oleh user tanpa `code.manage`, simpan ditolak. Dengan begitu, `page.json` tidak bisa dipakai untuk menyelundupkan kode.
- Widget `blade` hanya boleh menunjuk file di dalam folder halaman itu, divalidasi dengan `SafeFiles::resolve()`.
- URL divalidasi: hanya `http`, `https`, `mailto`, `tel`, `/path`, dan `#anchor`. `javascript:` dan `data:` ditolak.
- `richtext` lewat `HtmlSanitizer` saat simpan **dan** saat render. Saat render perlu karena AI bisa menulis file langsung.
- Ukuran `page.json` maksimal 512 KB (`SafeFiles::MAX_EDIT`). Batas 300 node per halaman.

## 12. Struktur kode

### File baru

```
app/Support/Builder/Builder.php          # load, migrate, save, publish, render
app/Support/Builder/Validator.php        # validasi spec + pesan dengan path node
app/Support/Builder/Widgets.php          # registry: core, theme/widgets, landing/<slug>/widgets, plugin
app/Support/Builder/Tokens.php           # token → class
app/Console/Commands/BuilderValidate.php
app/Console/Commands/BuilderSchema.php
app/Http/Controllers/Admin/BuilderController.php
resources/views/admin/builder/index.blade.php    # daftar halaman (DataTable)
resources/views/admin/builder/edit.blade.php     # editor
resources/views/builder/page.blade.php
resources/views/builder/layouts/{site,blank}.blade.php
resources/views/builder/section.blade.php
resources/views/builder/widgets/<name>/{widget.json,view.blade.php}
public/css/builder.css                   # class token untuk halaman publik
public/css/builder-admin.css
public/js/builder/{app,state,navigator,panel,preview,dnd,fields}.js
docs/BUILDER.md
docs/builder.schema.json
landing/example-builder/page.json        # contoh lengkap
tests/Feature/BuilderTest.php
tests/Feature/BuilderSecurityTest.php
```

### File yang diubah

```
app/Support/Landing.php        # entry page.json, render lewat Builder
app/Support/AdminMenu.php      # Design > Builder
routes/web.php                 # admin.design.builder.*
lang/id.json
docs/AI-GUIDE.md, landing/README.md
```

### Rute

```
GET  admin/design/builder                      daftar (code page + builder page)
POST admin/design/builder                      buat halaman baru (sheet: slug, title, template)
GET  admin/design/builder/{slug}               editor
GET  admin/design/builder/{slug}/spec          JSON draft + hash + definisi widget
POST admin/design/builder/{slug}/draft         simpan draft (hash wajib)
POST admin/design/builder/{slug}/render        render satu node atau section untuk preview
GET  admin/design/builder/{slug}/preview       halaman draft penuh untuk iframe
POST admin/design/builder/{slug}/publish
POST admin/design/builder/{slug}/discard
POST admin/design/builder/{slug}/duplicate
POST admin/design/builder/{slug}/delete
```

## 13. Fase

Fase ini dikerjakan setelah Fase 1–2 di `PLAN-DESIGN-TOOLS.md` (SafeFiles, menu Design, code editor), karena builder memakai semuanya.

| Fase | Isi | Hasil yang bisa dipakai | Perkiraan |
| --- | --- | --- | --- |
| **B1. Format dan renderer** | Format `page.json`, `Validator`, `Tokens` + `builder.css`, 8 widget dasar, layout `site`/`blank`, `Landing` mengenali `page.json`, `builder:validate`, `BUILDER.md`, schema, contoh halaman | **AI sudah bisa membuat halaman builder** lewat file, tanpa UI | 1,5–2 minggu |
| **B2. Editor dasar** | Daftar halaman, buat/duplikat/hapus, navigator, panel field, preview iframe + pilih node, draft/publish, konflik hash, tab bahasa | Manusia bisa mengedit secara visual | 2–3 minggu |
| **B3. Layout dan responsif** | `inner-section`, drag-and-drop, nilai per perangkat, preview 3 ukuran, undo/redo, media picker | Pengalaman setara Elementor dasar | 1,5–2 minggu |
| **B4. Jembatan kode** | Widget kustom (landing, theme, plugin), layout `custom`, widget `html`/`blade`, cek capability per node, sisa widget konten | Kode dan visual saling melengkapi | 1–1,5 minggu |
| **B5. Pengerasan** | Uji keamanan, cache render, uji aksesibilitas keyboard, dark mode, dokumentasi final, template awal (3–5 halaman siap pakai) | Siap rilis | 1 minggu |

Total sekitar **7–10 minggu** untuk satu developer. B1 sengaja didahulukan: format file yang matang adalah fondasi, dan AI sudah bisa memakainya sebelum UI selesai.

## 14. Pengujian

- **Format**: spec valid dirender benar. Field wajib yang kosong, widget tak dikenal, id ganda, dan kedalaman berlebih menghasilkan pesan dengan path node yang tepat. Node tak dikenal tetap ada setelah load lalu save.
- **Round-trip**: load lalu save tanpa perubahan menghasilkan file yang identik byte demi byte.
- **Bahasa**: `/promo` dan `/en/promo` menampilkan teks yang benar. Terjemahan kosong dirender kosong.
- **Prioritas entry**: `index.blade.php` menang atas `page.json`.
- **Keamanan**: user tanpa `code.manage` tidak bisa menambah atau mengubah node `html`/`blade` lewat `page.json`. URL `javascript:` ditolak. Rich text disanitasi saat render meski file ditulis langsung. Widget `blade` tidak bisa keluar dari folder halaman.
- **Draft**: draft tidak tampil publik. Publish membuat revisi. Konflik hash menghasilkan 409.
- **Ketahanan**: widget yang melempar exception tidak membuat halaman publik 500, dan error-nya tercatat.
- **Manual**: drag-and-drop dengan mouse dan keyboard, layar kecil, dark mode, AI mengedit file saat builder terbuka.

## 15. Risiko

| Risiko | Mitigasi |
| --- | --- |
| Format `page.json` berubah setelah banyak halaman dibuat | Field `version` + migrasi saat baca. Format dibekukan di akhir B1. |
| AI menulis spec yang rusak | Render toleran (P6), `builder:validate`, pesan dengan path node, revisi |
| Builder dan AI menyimpan bersamaan | Hash wajib, 409, polling hash, draft terpisah dari live |
| Widget core berubah saat update dan merusak tampilan | Widget core diberi versi. Field hanya boleh ditambah, tidak diubah artinya. Perubahan besar dibuat sebagai widget baru. |
| Builder tumbuh menjadi page builder yang rapuh | Tanpa posisi bebas, tanpa CSS per elemen, kedalaman maksimum 2 |
| Performa iframe dengan halaman panjang | Render ulang per section, bukan seluruh halaman |

## 16. Keputusan yang perlu disetujui

| # | Pertanyaan | Rekomendasi |
| --- | --- | --- |
| 1 | Sumber halaman | `page.json`. Builder tidak menulis Blade. |
| 2 | Draft | File `page.draft.json` di folder halaman, sehingga AI juga bisa mengedit draft |
| 3 | Terjemahan kosong | Dirender kosong + peringatan di builder, sesuai `Locales::text()`. Alternatifnya fallback ke bahasa default. |
| 4 | Widget kustom | Di `landing/<slug>/widgets/`, `theme/widgets/`, dan plugin |
| 5 | Form dengan submit | Tidak di MVP |
| 6 | Konversi code page lama (`dyskflow`, `kontak`) ke builder | Tidak otomatis. Dibuatkan template builder yang setara bila perlu. |
| 7 | "Eject" builder page menjadi Blade statis | Tidak di MVP. Bisa ditambah nanti sebagai jalan satu arah. |
