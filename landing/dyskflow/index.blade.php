@php
    $en = app()->getLocale() === 'en';
    $t = fn (string $id, string $english) => $en ? $english : $id;
    $repo = \App\Support\UpdateService::repo();

    $benefits = [
        ['01', $t('Tanpa build, tanpa terminal', 'No build, no terminal'), $t('Tidak perlu Node, Vite, atau Composer di hosting. CSS dan JavaScript adalah file biasa yang langsung jalan.', 'No Node, Vite or Composer on the host. CSS and JavaScript are plain files that simply run.')],
        ['02', $t('SEO sudah bawaan', 'SEO built in'), $t('Meta, canonical, Open Graph, JSON-LD, sitemap, robots, dan RSS. Editor menampilkan pratinjau hasil pencarian dan daftar periksa.', 'Meta tags, canonical, Open Graph, JSON-LD, sitemap, robots and RSS. The editor shows a search preview and a checklist.')],
        ['03', $t('Landing page bebas dikoding', 'Landing pages coded your way'), $t('Tulis sendiri atau dengan AI di folder terpisah. HTML, CSS, dan JavaScript Anda tidak dibatasi page builder.', 'Write them by hand or with AI in their own folder. Your HTML, CSS and JavaScript are not limited by a page builder.')],
        ['04', $t('Plugin tanpa mengubah inti', 'Plugins without touching core'), $t('Tiap plugin punya foldernya sendiri, memakai hook, dan mendapat halaman pengaturan otomatis.', 'Each plugin has its own folder, uses hooks, and gets a settings page automatically.')],
        ['05', $t('Ringan untuk pengunjung', 'Light for visitors'), $t('Pengunjung baru tidak menerima cookie, sehingga halaman publik bisa disimpan di cache CDN.', 'New visitors receive no cookies, so public pages can be cached by a CDN.')],
        ['06', $t('Admin yang tertata', 'A tidy admin'), $t('Pencarian global, notifikasi, peran pengguna, backup, mode gelap, serta bahasa Indonesia dan Inggris.', 'Global search, notifications, user roles, backups, dark mode, and Indonesian and English.')],
    ];
    $steps = [
        [$t('Unggah file', 'Upload the files'), $t('Salin file DyskFlow ke hosting, tanpa folder vendor.', 'Copy the DyskFlow files to your host, without the vendor folder.')],
        [$t('Tekan Pasang sekarang', 'Press Install now'), $t('Situs mengunduh dependensinya sendiri dari rilis GitHub.', 'The site downloads its own dependencies from the GitHub release.')],
        [$t('Ikuti wizard', 'Follow the wizard'), $t('Isi database dan akun admin, lalu mulai menulis.', 'Enter the database and admin account, then start writing.')],
    ];
    $latest = cms_posts(3);
    $kept = ['.env', 'storage/', 'landing/', 'plugins/', 'public/uploads/', 'cms.json'];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! $seo !!}
    <link rel="stylesheet" href="{{ landing_asset('assets/style.css') }}">
</head>
<body>
    <a class="skip" href="#kelebihan">{{ $t('Lewati ke isi', 'Skip to content') }}</a>

    <header class="bar">
        <div class="wrap bar-in">
            <span class="brand"><img src="{{ landing_asset('assets/logo-mark.svg') }}" alt="" width="28" height="28"> DyskFlow</span>
            <nav aria-label="{{ $t('Menu halaman', 'Page menu') }}">
                <a href="#kelebihan">{{ $t('Kelebihan', 'Benefits') }}</a>
                <a href="#pembaruan">{{ $t('Pembaruan', 'Updates') }}</a>
                <a href="#syarat">{{ $t('Syarat', 'Requirements') }}</a>
                <a href="{{ site_route('blog') }}">{{ $t('Artikel', 'Articles') }}</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero wrap">
            <div class="hero-copy">
                <p class="kicker">{{ $t('CMS berbasis Laravel', 'A CMS built on Laravel') }}</p>
                <h1>{{ $t('Pasang Laravel di hosting biasa, tanpa membuka terminal.', 'Run Laravel on ordinary hosting, without opening a terminal.') }}</h1>
                <p class="lead">{{ $t('DyskFlow mengelola artikel, halaman, media, dan SEO seperti CMS yang sudah Anda kenal, sementara landing page tetap bebas Anda koding sendiri.', 'DyskFlow manages articles, pages, media and SEO like the CMS you already know, while your landing pages stay free for you to code.') }}</p>
                <div class="actions">
                    <a class="btn btn-main" href="#kelebihan">{{ $t('Lihat kelebihannya', 'See the benefits') }}</a>
                    @if ($repo)<a class="btn btn-line" href="https://github.com/{{ $repo }}" rel="noopener">GitHub</a>@endif
                </div>
            </div>
            <ol class="steps" aria-label="{{ $t('Cara memasang', 'How to install') }}">
                @foreach ($steps as $i => [$title, $text])
                    <li><span class="num" aria-hidden="true">{{ $i + 1 }}</span><div><strong>{{ $title }}</strong><p>{{ $text }}</p></div></li>
                @endforeach
            </ol>
        </section>

        <section class="section wrap" id="kelebihan">
            <h2>{{ $t('Yang membuatnya berbeda', 'What makes it different') }}</h2>
            <div class="grid">
                @foreach ($benefits as [$no, $title, $text])
                    <article class="benefit">
                        <span class="no" aria-hidden="true">{{ $no }}</span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="band" id="pembaruan">
            <div class="wrap band-in">
                <div>
                    <h2>{{ $t('Perbarui dari admin, data Anda tetap utuh', 'Update from the admin, your data stays intact') }}</h2>
                    <p>{{ $t('Saat ada rilis baru, notifikasi muncul di admin. Satu tombol membuat backup database, mengambil kode terbaru, dan menjalankan migrasi. Dependensi hanya diganti bila memang berubah.', 'When a release is out, a notification appears in the admin. One button backs up the database, fetches the new code and runs migrations. Dependencies are replaced only when they changed.') }}</p>
                </div>
                <div class="kept">
                    <p class="kept-title">{{ $t('Tidak pernah ditimpa pembaruan', 'Never overwritten by an update') }}</p>
                    <ul>
                        @foreach ($kept as $path)<li><code>{{ $path }}</code></li>@endforeach
                    </ul>
                </div>
            </div>
        </section>

        <section class="section wrap needs" id="syarat">
            <h2>{{ $t('Yang dibutuhkan hosting', 'What your host needs') }}</h2>
            <dl>
                <div><dt>PHP</dt><dd>8.3 {{ $t('atau lebih baru', 'or newer') }}</dd></div>
                <div><dt>{{ $t('Database', 'Database') }}</dt><dd>MySQL {{ $t('atau', 'or') }} SQLite</dd></div>
                <div><dt>{{ $t('Ekstensi', 'Extensions') }}</dt><dd>mbstring, openssl, fileinfo, dom, zip</dd></div>
                <div><dt>{{ $t('Server', 'Server') }}</dt><dd>Apache, LiteSpeed, {{ $t('atau', 'or') }} Nginx</dd></div>
            </dl>
        </section>

        @if ($latest->isNotEmpty())
            <section class="section wrap latest" id="artikel">
                <div class="latest-head">
                    <h2>{{ $t('Artikel terbaru', 'Latest articles') }}</h2>
                    <a href="{{ site_route('blog') }}">{{ $t('Semua artikel', 'All articles') }}</a>
                </div>
                <div class="latest-list">
                    @foreach ($latest as $post)
                        <a class="latest-item" href="{{ $post->url() }}">
                            <span class="latest-date">{{ cms_date($post->published_at, 'd M Y') }}</span>
                            <strong>{{ $post->title }}</strong>
                            <span class="latest-text">{{ $post->excerpt ?: cms_excerpt($post->content, 120) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    <footer class="foot">
        <div class="wrap foot-in">
            <span>{{ setting('site_title', 'DyskFlow') }}</span>
            @if (setting('contact_email'))<a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a>@endif
        </div>
    </footer>
</body>
</html>
