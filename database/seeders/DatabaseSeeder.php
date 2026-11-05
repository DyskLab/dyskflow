<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Term;
use App\Models\User;
use App\Support\Locales;
use App\Support\PrivacyGuide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Sample content for trying the CMS after installing: php artisan db:seed
 * Needs an installed site, because the content belongs to its first user.
 * Safe to run again: what already exists is left as it is.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::orderBy('id')->first();
        if (! $user) {
            $this->command?->error('Finish the install wizard first: sample content needs a user.');

            return;
        }

        $base = Locales::default();
        $second = Locales::second();
        $text = fn (string $id, string $en, string $locale) => $locale === 'id' ? $id : $en;

        $guides = $this->term('category', ['id' => ['Panduan', 'panduan', 'Cara memakai dan mengelola DyskFlow.'], 'en' => ['Guides', 'guides', 'How to use and manage DyskFlow.']], $base, $second);
        $news = $this->term('category', ['id' => ['Berita', 'berita', 'Kabar terbaru tentang DyskFlow.'], 'en' => ['News', 'news', 'The latest about DyskFlow.']], $base, $second);
        $laravel = $this->term('tag', ['id' => ['Laravel', 'laravel', null], 'en' => ['Laravel', 'laravel', null]], $base, $second);
        $seo = $this->term('tag', ['id' => ['SEO', 'seo', null], 'en' => ['SEO', 'seo', null]], $base, $second);

        $cover = $this->media($user);

        $welcome = $this->post($user, 'article', [
            'id' => ['Selamat datang di DyskFlow', 'selamat-datang-di-dyskflow', 'Artikel contoh yang menunjukkan kategori, tag, komentar, dan terjemahan.', '<p>Ini artikel contoh dari seeder. Ubah atau hapus kapan saja di admin.</p><h2>Yang bisa dicoba</h2><ul><li>Terjemahkan artikel ke bahasa kedua.</li><li>Setujui komentar yang menunggu.</li><li>Lihat skor SEO di editor.</li></ul>'],
            'en' => ['Welcome to DyskFlow', 'welcome-to-dyskflow', 'A sample article that shows categories, tags, comments and translations.', '<p>This is a sample article from the seeder. Change or delete it any time in the admin.</p><h2>Things to try</h2><ul><li>Translate the article into the second language.</li><li>Approve the waiting comment.</li><li>See the SEO score in the editor.</li></ul>'],
        ], $base, $second, ['featured_image' => $cover->path, 'published_at' => now()->subDays(2)]);
        $welcome->terms()->syncWithoutDetaching([$guides->id, $laravel->id]);

        $updates = $this->post($user, 'article', [
            'id' => ['Cara kerja pembaruan satu tombol', 'cara-kerja-pembaruan-satu-tombol', 'Apa yang terjadi saat Anda menekan Perbarui sekarang.', '<p>DyskFlow membuat backup database, mengambil kode dari rilis terbaru, lalu menjalankan migrasi.</p><p>Folder <code>landing</code>, <code>plugins</code>, dan <code>public/uploads</code> tidak pernah ditimpa.</p>'],
            'en' => ['How one-click updates work', 'how-one-click-updates-work', 'What happens when you press Update now.', '<p>DyskFlow backs up the database, fetches the code of the latest release, then runs migrations.</p><p>The <code>landing</code>, <code>plugins</code> and <code>public/uploads</code> folders are never overwritten.</p>'],
        ], $base, $second, ['published_at' => now()->subDay()]);
        $updates->terms()->syncWithoutDetaching([$news->id, $seo->id]);

        $this->privacyPage($user, $base, $second);

        if (! Comment::withTrashed()->where('post_id', $welcome->id)->exists()) {
            $welcome->comments()->create(['author_name' => 'Budi', 'author_email' => 'budi@example.com', 'content' => $text('Artikel yang membantu, terima kasih.', 'A helpful article, thank you.', $base), 'status' => 'approved']);
            $welcome->comments()->create(['author_name' => 'Sari', 'author_email' => 'sari@example.com', 'content' => $text('Komentar ini menunggu persetujuan.', 'This comment is waiting for approval.', $base), 'status' => 'pending']);
        }

        $this->command?->info('Sample content is ready.');
    }

    /** A draft built from what the site really collects. It has words in square brackets for the owner to replace, so it stays unpublished. */
    private function privacyPage(User $user, string $base, string $second): void
    {
        $current = setting('privacy_page_id');
        if ($current && Post::where('type', 'page')->whereKey($current)->exists()) {
            return;
        }

        $version = function (string $locale) {
            $title = __('Privacy Policy', [], $locale);

            return [$title, Str::slug($title), null, PrivacyGuide::draft($locale)];
        };
        $page = $this->post($user, 'page', [$base => $version($base), $second => $version($second)], $base, $second, ['status' => 'draft', 'allow_comments' => false]);

        if (! $page->trashed()) {
            Setting::write(['privacy_page_id' => (string) $page->id]);
        }
    }

    private function term(string $taxonomy, array $versions, string $base, string $second): Term
    {
        [$name, $slug, $description] = $versions[$base];
        $term = Term::firstOrCreate(['taxonomy' => $taxonomy, 'slug' => $slug], ['name' => $name, 'description' => $description]);

        [$name, $slug, $description] = $versions[$second];
        $term->translations()->firstOrCreate(['locale' => $second], ['taxonomy' => $taxonomy, 'name' => $name, 'slug' => $slug, 'description' => $description]);

        return $term;
    }

    private function post(User $user, string $type, array $versions, string $base, string $second, array $extra): Post
    {
        [$title, $slug, $excerpt, $content] = $versions[$base];
        $post = Post::withTrashed()->firstOrCreate(['type' => $type, 'slug' => $slug], $extra + [
            'user_id' => $user->id, 'title' => $title, 'excerpt' => $excerpt, 'content' => $content, 'status' => 'published',
        ]);

        [$title, $slug, $excerpt, $content] = $versions[$second];
        $post->translations()->firstOrCreate(['locale' => $second], [
            'type' => $type, 'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => $content, 'status' => $extra['status'] ?? 'published',
        ]);

        return $post;
    }

    private function media(User $user): Media
    {
        $path = 'uploads/sample/dyskflow-logo.svg';
        File::ensureDirectoryExists(public_path('uploads/sample'));
        File::copy(public_path('img/logo-mark.svg'), public_path($path));

        return Media::firstOrCreate(['path' => $path], [
            'filename' => 'dyskflow-logo.svg', 'mime' => 'image/svg+xml', 'size' => filesize(public_path($path)),
            'width' => 100, 'height' => 100, 'title' => 'DyskFlow', 'alt' => 'DyskFlow', 'user_id' => $user->id,
        ]);
    }
}
