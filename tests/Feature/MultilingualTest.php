<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Term;
use App\Models\User;
use App\Support\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MultilingualTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->storage.'/app');
        File::ensureDirectoryExists($this->storage.'/framework/views');
        $this->app->useStoragePath($this->storage);
        file_put_contents($this->storage.'/app/.installed', 'test');

        $role = Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true]);
        $this->admin = User::create(['role_id' => $role->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        Setting::write(['site_title' => 'Situs', 'locale' => 'id', 'timezone' => 'UTC', 'tagline' => 'Slogan Indonesia']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function article(array $attributes = [], ?array $translation = []): Post
    {
        $post = Post::create($attributes + [
            'type' => 'article', 'title' => 'Halo Dunia', 'slug' => 'halo-dunia', 'content' => '<p>Isi bahasa Indonesia</p>',
            'status' => 'published', 'published_at' => now()->subDay(), 'user_id' => $this->admin->id, 'allow_comments' => true,
        ]);
        if ($translation !== null) {
            $post->translations()->create($translation + [
                'type' => $post->type, 'locale' => 'en', 'title' => 'Hello World', 'slug' => 'hello-world',
                'content' => '<p>English content</p>', 'status' => 'published',
            ]);
        }

        return $post;
    }

    private function page(array $attributes = [], ?array $translation = []): Post
    {
        return $this->article($attributes + ['type' => 'page', 'title' => 'Tentang', 'slug' => 'tentang'], $translation === null ? null : $translation + ['title' => 'About', 'slug' => 'about']);
    }

    public function test_translation_has_its_own_url_and_content(): void
    {
        $this->article();

        $this->get('/en/blog/hello-world')->assertOk()->assertSee('Hello World')->assertSee('English content')->assertDontSee('Isi bahasa Indonesia')->assertSee('<html lang="en">', false);
        $this->get('/en')->assertOk()->assertSee('Hello World')->assertDontSee('Halo Dunia');
        $this->get('/en/blog')->assertOk()->assertSee('/en/blog/hello-world', false);
    }

    public function test_old_urls_keep_working_in_the_default_language(): void
    {
        $this->article();
        $this->page();

        $this->get('/blog/halo-dunia')->assertOk()->assertSee('Isi bahasa Indonesia')->assertDontSee('English content')->assertSee('<html lang="id">', false);
        $this->get('/tentang')->assertOk()->assertSee('Tentang');
        $this->get('/blog')->assertOk()->assertSee('Halo Dunia');
    }

    public function test_no_translation_means_no_page_and_no_fallback(): void
    {
        $this->article(['title' => 'Hanya Indonesia', 'slug' => 'hanya-indonesia'], null);
        $this->article(['title' => 'Konsep', 'slug' => 'konsep'], ['slug' => 'draft-one', 'title' => 'Draft One', 'status' => 'draft']);

        $this->get('/en/blog/hanya-indonesia')->assertNotFound();
        $this->get('/en/blog/draft-one')->assertNotFound();
        $this->get('/en/blog/konsep')->assertNotFound();
        $this->get('/en/blog')->assertOk()->assertDontSee('Hanya Indonesia')->assertDontSee('Draft One');
        $this->get('/en/search?q=Hanya')->assertOk()->assertDontSee('hanya-indonesia');
    }

    public function test_a_published_translation_of_a_draft_stays_hidden(): void
    {
        $this->article(['status' => 'draft']);

        $this->get('/en/blog/hello-world')->assertNotFound();
        $this->get('/en')->assertDontSee('Hello World');
    }

    public function test_prefix_of_the_default_language_redirects_to_the_one_address(): void
    {
        $this->article();

        $this->get('/id/blog/halo-dunia?x=1')->assertStatus(301)->assertRedirect(url('blog/halo-dunia?x=1'));
        $this->get('/id')->assertStatus(301)->assertRedirect(url('/'));
    }

    public function test_hreflang_is_reciprocal_with_self_reference_and_x_default(): void
    {
        $this->article();
        $id = url('blog/halo-dunia');
        $en = url('en/blog/hello-world');

        foreach (['/blog/halo-dunia', '/en/blog/hello-world'] as $path) {
            $response = $this->get($path);
            $response->assertSee('<link rel="alternate" hreflang="id" href="'.$id.'">', false);
            $response->assertSee('<link rel="alternate" hreflang="en" href="'.$en.'">', false);
            $response->assertSee('<link rel="alternate" hreflang="x-default" href="'.$id.'">', false);
        }
        $this->get('/blog/halo-dunia')->assertSee('<link rel="canonical" href="'.$id.'">', false);
        $this->get('/en/blog/hello-world')->assertSee('<link rel="canonical" href="'.$en.'">', false);
    }

    public function test_no_hreflang_without_a_second_indexable_version(): void
    {
        $this->article(['slug' => 'satu'], null);
        $this->article(['slug' => 'dua'], ['slug' => 'two', 'title' => 'Two', 'status' => 'draft']);
        $this->article(['slug' => 'tiga'], ['slug' => 'three', 'title' => 'Three', 'noindex' => true]);
        $this->article(['slug' => 'empat'], ['slug' => 'four', 'title' => 'Four', 'canonical_url' => 'https://example.test/four']);

        foreach (['/blog/satu', '/blog/dua', '/blog/tiga', '/en/blog/three', '/blog/empat', '/en/blog/four'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('hreflang="x-default"', false);
        }
        $this->get('/en/blog/three')->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_lists_have_hreflang_on_the_first_page_only_when_the_second_language_has_articles(): void
    {
        $this->get('/blog')->assertDontSee('hreflang="x-default"', false);
        $this->get('/en/blog')->assertOk()->assertSee('<meta name="robots" content="noindex">', false)->assertDontSee('hreflang="x-default"', false);

        $this->article();
        Setting::write(['posts_per_page' => '1']);
        $this->article(['slug' => 'kedua'], ['slug' => 'second', 'title' => 'Second']);

        $this->get('/blog')->assertSee('<link rel="alternate" hreflang="en" href="'.url('en/blog').'">', false);
        $this->get('/en/blog')->assertSee('<link rel="alternate" hreflang="id" href="'.url('blog').'">', false)->assertDontSee('name="robots"', false);
        $this->get('/en')->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/').'">', false);
        $this->get('/en/blog?page=2')->assertOk()->assertDontSee('hreflang="x-default"', false)->assertSee('<link rel="canonical" href="'.url('en/blog').'?page=2">', false);
    }

    public function test_terms_are_translated_and_untranslated_ones_are_left_out(): void
    {
        $post = $this->article();
        $translated = Term::create(['taxonomy' => 'category', 'name' => 'Berita', 'slug' => 'berita']);
        $translated->translations()->create(['taxonomy' => 'category', 'locale' => 'en', 'name' => 'News', 'slug' => 'news']);
        $plain = Term::create(['taxonomy' => 'category', 'name' => 'Lokal', 'slug' => 'lokal']);
        $post->terms()->sync([$translated->id, $plain->id]);

        $this->get('/category/berita')->assertOk()->assertSee('Halo Dunia')->assertSee('hreflang="en" href="'.url('en/category/news').'"', false);
        $this->get('/en/category/news')->assertOk()->assertSee('News')->assertSee('Hello World')->assertSee('hreflang="id" href="'.url('category/berita').'"', false);
        $this->get('/en/category/lokal')->assertNotFound();
        $this->get('/en/blog/hello-world')->assertSee(url('en/category/news'), false)->assertDontSee('Lokal');
    }

    public function test_a_homepage_page_lives_at_the_root_of_each_language(): void
    {
        $page = $this->page();
        Setting::write(['home_type' => 'page', 'home_page_id' => (string) $page->id]);

        $this->get('/')->assertOk()->assertSee('Tentang')->assertSee('hreflang="en" href="'.url('en').'"', false);
        $this->get('/en')->assertOk()->assertSee('About')->assertSee('<link rel="canonical" href="'.url('en').'">', false);
        $this->get('/tentang')->assertStatus(301)->assertRedirect(url('/'));
        $this->get('/en/about')->assertStatus(301)->assertRedirect(url('en'));

        $page->translations()->delete();
        $this->get('/en')->assertOk()->assertDontSee('hreflang="x-default"', false);
        $this->get('/')->assertOk()->assertDontSee('hreflang="x-default"', false);
    }

    public function test_sitemap_lists_each_version_with_its_alternates(): void
    {
        $this->article();
        $this->article(['slug' => 'satu'], null);
        $this->article(['slug' => 'tiga'], ['slug' => 'three', 'title' => 'Three', 'noindex' => true]);
        $this->article(['slug' => 'konsep', 'status' => 'draft'], ['slug' => 'draft', 'title' => 'Draft']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertNotFalse(simplexml_load_string($xml), 'The sitemap must be valid XML');
        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
        $this->assertStringContainsString('<loc>'.url('en/blog/hello-world').'</loc>', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="en" href="'.url('en/blog/hello-world').'"/>', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="x-default" href="'.url('blog/halo-dunia').'"/>', $xml);
        $this->assertStringContainsString('<loc>'.url('blog/satu').'</loc>', $xml);
        $this->assertStringNotContainsString('/en/blog/satu', $xml);
        $this->assertStringNotContainsString('/en/blog/three', $xml);
        $this->assertStringNotContainsString('konsep', $xml);
        $this->assertSame(1, substr_count($xml, '<loc>'.url('/').'</loc>'));
    }

    public function test_language_pages_send_no_cookies_and_use_a_plain_link(): void
    {
        $this->article();
        Setting::write(['comments_enabled' => '0']);

        $response = $this->get('/en/blog');
        $response->assertOk()->assertHeaderMissing('Set-Cookie')->assertDontSee('name="_token"', false);
        $response->assertSee('href="'.url('blog').'" hreflang="id"', false);
        $this->get('/blog/halo-dunia')->assertHeaderMissing('Set-Cookie')->assertSee('href="'.url('en/blog/hello-world').'" hreflang="en"', false);
    }

    public function test_session_language_does_not_change_public_pages(): void
    {
        $this->article();

        $this->withSession(['locale' => 'en'])->get('/blog/halo-dunia')->assertSee('<html lang="id">', false);
    }

    public function test_settings_are_translated_and_never_mixed(): void
    {
        $this->article();

        $this->get('/en')->assertDontSee('Slogan Indonesia');
        Setting::write(['tagline_translated' => 'English tagline']);
        $this->get('/en')->assertSee('English tagline')->assertDontSee('Slogan Indonesia');
        $this->get('/')->assertSee('Slogan Indonesia')->assertDontSee('English tagline');
    }

    public function test_feed_is_per_language(): void
    {
        $this->article();
        $this->article(['title' => 'Hanya Indonesia', 'slug' => 'hanya'], null);

        $this->get('/en/feed')->assertOk()->assertSee('<language>en</language>', false)->assertSee('Hello World')->assertDontSee('Hanya Indonesia');
        $this->get('/feed')->assertOk()->assertSee('<language>id</language>', false)->assertSee('Hanya Indonesia')->assertDontSee('Hello World');
    }

    public function test_comments_are_shared_with_the_original(): void
    {
        $post = $this->article();
        Setting::write(['comments_moderation' => '0', 'comments_require_email' => '0']);

        $this->from('/en/blog/hello-world')->post('/en/blog/hello-world/comments', ['author_name' => 'Ann', 'content' => 'Nice one'])->assertRedirect('/en/blog/hello-world');

        $this->assertSame(1, $post->comments()->count());
        $this->get('/blog/halo-dunia')->assertSee('Nice one');
        $this->get('/en/blog/hello-world')->assertSee('Nice one');
        $this->post('/en/blog/halo-dunia/comments', ['author_name' => 'Ann', 'content' => 'Nice one'])->assertNotFound();
    }

    public function test_admin_saves_sanitizes_and_deletes_a_translation(): void
    {
        $post = $this->article([], null);
        $this->actingAs($this->admin);

        $this->get("/admin/articles/{$post->id}/translation")->assertOk()->assertSee('Halo Dunia')->assertSee('English');
        $this->post("/admin/articles/{$post->id}/translation", [
            'title' => 'Hello World', 'slug' => '', 'status' => 'published', 'content' => '<p>Safe</p><script>alert(1)</script>',
        ])->assertRedirect("/admin/articles/{$post->id}/translation");

        $translation = $post->translations()->firstOrFail();
        $this->assertSame('hello-world', $translation->slug);
        $this->assertStringNotContainsString('script', $translation->content);
        $this->assertSame($post->slug, $post->fresh()->slug);

        $this->get("/admin/articles/{$post->id}/edit")->assertOk()->assertSee('Ubah terjemahan');
        $this->get('/admin/articles')->assertOk()->assertSee("/admin/articles/{$post->id}/translation", false);

        $this->post("/admin/articles/{$post->id}/translation/delete")->assertRedirect();
        $this->assertSame(0, $post->translations()->count());
        $this->get('/en/blog/hello-world')->assertNotFound();
    }

    public function test_translation_slugs_are_unique_and_page_slugs_avoid_system_routes(): void
    {
        $first = $this->article();
        $second = $this->article(['slug' => 'lain'], null);
        $page = $this->page([], null);
        $this->actingAs($this->admin);

        $this->post("/admin/articles/{$second->id}/translation", ['title' => 'Hello World', 'status' => 'draft']);
        $this->assertSame('hello-world-2', $second->translations()->first()->slug);
        $this->assertSame('hello-world', $first->translations()->first()->slug);

        $this->post("/admin/pages/{$page->id}/translation", ['title' => 'Blog', 'status' => 'draft'])->assertSessionHasErrors('slug');
        $this->post('/admin/pages', ['title' => 'En', 'slug' => 'en', 'status' => 'draft'])->assertSessionHasErrors('slug');
    }

    public function test_authors_may_only_translate_their_own_articles(): void
    {
        $role = Role::create(['name' => 'Author', 'slug' => 'author', 'capabilities' => ['articles.write']]);
        $author = User::create(['role_id' => $role->id, 'name' => 'Author', 'email' => 'author@example.test', 'password' => 'password123']);
        $own = $this->article(['slug' => 'punya-saya', 'user_id' => $author->id], null);
        $other = $this->article(['slug' => 'punya-lain'], null);
        $this->actingAs($author);

        $this->get("/admin/articles/{$own->id}/translation")->assertOk();
        $this->post("/admin/articles/{$own->id}/translation", ['title' => 'Mine', 'status' => 'draft'])->assertRedirect();
        $this->get("/admin/articles/{$other->id}/translation")->assertForbidden();
        $this->post("/admin/articles/{$other->id}/translation", ['title' => 'No', 'status' => 'draft'])->assertForbidden();
        $this->post("/admin/articles/{$other->id}/translation/delete")->assertForbidden();
        $this->get("/admin/pages/{$own->id}/translation")->assertForbidden();
        $this->assertSame(0, $other->translations()->count());
    }

    public function test_term_form_saves_and_removes_a_translation(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/categories', ['name' => 'Berita', 'translated_name' => 'News'])->assertRedirect();
        $term = Term::where('slug', 'berita')->firstOrFail();
        $this->assertSame('news', $term->translations()->first()->slug);
        $this->get("/admin/categories/{$term->id}/edit")->assertOk()->assertSee('name="translated_name" value="News"', false);

        $this->post("/admin/categories/{$term->id}", ['name' => 'Berita', 'translated_name' => ''])->assertRedirect();
        $this->assertSame(0, $term->translations()->count());
    }

    public function test_translations_survive_export_and_import(): void
    {
        $post = $this->article();
        $term = Term::create(['taxonomy' => 'category', 'name' => 'Berita', 'slug' => 'berita']);
        $term->translations()->create(['taxonomy' => 'category', 'locale' => 'en', 'name' => 'News', 'slug' => 'news']);
        $this->actingAs($this->admin);

        $json = $this->post('/admin/settings/tools/export', ['include' => ['articles']])->assertOk()->streamedContent();

        $post->forceDelete();
        $term->delete();
        $this->assertSame(0, \App\Models\PostTranslation::count(), 'Deleting a post removes its translations');

        $file = UploadedFile::fake()->createWithContent('export.json', $json);
        $this->post('/admin/settings/tools/import', ['file' => $file])->assertSessionHas('success');

        $this->assertSame('hello-world', Post::where('slug', 'halo-dunia')->first()->translations()->first()->slug);
        $this->assertSame('News', Term::where('slug', 'berita')->first()->translations()->first()->name);
        auth()->logout();
        $this->get('/en/blog/hello-world')->assertOk()->assertSee('English content');
    }

    public function test_translations_survive_backup_and_restore(): void
    {
        $post = $this->article();
        $name = BackupService::create(false);

        $post->translations()->delete();
        BackupService::restore(BackupService::path($name));

        $this->assertSame('hello-world', Post::first()->translations()->first()->slug);
    }
}
