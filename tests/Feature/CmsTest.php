<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Term;
use App\Models\User;
use App\Support\AdminMenu;
use App\Support\BackupService;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated storage, so tests never touch the real install marker or backups.
        $this->storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->storage.'/app');
        File::ensureDirectoryExists($this->storage.'/framework/views');
        $this->app->useStoragePath($this->storage);
        file_put_contents($this->storage.'/app/.installed', 'test');

        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true]);
        $this->admin = User::create(['role_id' => $adminRole->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        Setting::write(['site_title' => 'Test Site', 'locale' => 'en', 'timezone' => 'UTC']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function article(array $attributes = []): Post
    {
        return Post::create($attributes + [
            'type' => 'article', 'title' => 'Hello', 'slug' => 'hello-'.bin2hex(random_bytes(2)),
            'status' => 'published', 'published_at' => now()->subDay(), 'user_id' => $this->admin->id,
        ]);
    }

    public function test_everything_redirects_to_the_wizard_until_installed(): void
    {
        unlink($this->storage.'/app/.installed');

        $this->get('/')->assertRedirect('install');
        $this->get('/admin/login')->assertRedirect('install');
        $this->get('/install')->assertOk();
    }

    public function test_wizard_is_closed_after_install(): void
    {
        $this->get('/install')->assertNotFound();
        $this->get('/install/database')->assertNotFound();
    }

    public function test_sanitizer_removes_dangerous_html_and_keeps_formatting(): void
    {
        $clean = HtmlSanitizer::clean('<h2 onclick="x()">T</h2><script>alert(1)</script><a href="javascript:alert(1)">a</a><a href=" java&#10;script:alert(1)">b</a><img src="x.png" onerror="y()"><p style="color:red">p</p>');

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('style', $clean);
        $this->assertStringContainsString('<h2>T</h2>', $clean);
        $this->assertStringContainsString('src="x.png"', $clean);
    }

    public function test_saving_an_article_sanitizes_content_and_makes_a_unique_slug(): void
    {
        $this->article(['slug' => 'same-title']);
        $category = Term::create(['taxonomy' => 'category', 'name' => 'News', 'slug' => 'news']);

        $this->actingAs($this->admin)->post('/admin/articles', [
            'title' => 'Same Title', 'status' => 'published',
            'content' => '<p>ok</p><script>bad()</script>',
            'categories' => [$category->id], 'tags' => 'alpha, beta',
        ])->assertRedirect();

        $post = Post::where('slug', 'same-title-2')->firstOrFail();
        $this->assertSame('<p>ok</p>', $post->content);
        $this->assertSame(3, $post->terms()->count());
    }

    public function test_page_cannot_use_a_reserved_slug(): void
    {
        $this->actingAs($this->admin)->post('/admin/pages', ['title' => 'x', 'slug' => 'admin', 'status' => 'draft'])
            ->assertSessionHasErrors('slug');
    }

    public function test_table_sorts_filters_searches_and_paginates(): void
    {
        foreach (range(1, 12) as $i) {
            $this->article(['title' => sprintf('Post %02d', $i), 'status' => $i === 1 ? 'draft' : 'published']);
        }
        $this->actingAs($this->admin);

        $this->get('/admin/articles?sort=title&dir=desc&per_page=10')
            ->assertOk()
            ->assertSeeInOrder(['Post 12', 'Post 03'])
            ->assertDontSee('Post 01')
            ->assertSee('name="ids[]"', false);

        $this->get('/admin/articles?per_page=10&sort=title&dir=desc&page=2')->assertSee('Post 01');
        $this->get('/admin/articles?f[status]=draft')->assertSee('Post 01')->assertDontSee('Post 02');
        $this->get('/admin/articles?q=Post+07')->assertSee('Post 07')->assertDontSee('Post 08');
        // A sort column outside the allow-list is ignored rather than reaching SQL.
        $this->get('/admin/articles?sort=password')->assertOk();
    }

    public function test_author_only_reaches_own_articles_and_allowed_areas(): void
    {
        $role = Role::create(['name' => 'Author', 'slug' => 'author', 'capabilities' => ['articles.write']]);
        $author = User::create(['role_id' => $role->id, 'name' => 'A', 'email' => 'a@example.test', 'password' => 'password123']);
        $theirs = $this->article(['title' => 'Someone else', 'user_id' => $this->admin->id]);
        $mine = $this->article(['title' => 'My own', 'user_id' => $author->id]);
        $this->actingAs($author);

        $this->get('/admin/articles')->assertSee('My own')->assertDontSee('Someone else');
        $this->get("/admin/articles/{$theirs->id}/edit")->assertForbidden();
        $this->get("/admin/articles/{$mine->id}/edit")->assertOk();
        $this->post('/admin/articles/bulk', ['ids' => [$theirs->id], 'action' => 'delete']);
        $this->assertModelExists($theirs);
        foreach (['/admin/pages', '/admin/settings/general', '/admin/users', '/admin/settings/backups', '/admin/settings/update'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_role_form_cannot_grant_more_than_the_editor_has(): void
    {
        $role = Role::create(['name' => 'Manager', 'slug' => 'manager', 'capabilities' => ['users.manage']]);
        $manager = User::create(['role_id' => $role->id, 'name' => 'M', 'email' => 'm@example.test', 'password' => 'password123']);

        $this->actingAs($manager)->post('/admin/roles', ['name' => 'Sneaky', 'capabilities' => ['users.manage', 'updates.manage']]);

        $this->assertSame(['users.manage'], Role::where('slug', 'sneaky')->value('capabilities'));
        $adminRole = Role::where('slug', 'administrator')->first();
        $this->post('/admin/users', ['name' => 'X', 'email' => 'x@example.test', 'role_id' => $adminRole->id, 'password' => 'password123'])
            ->assertSessionHasErrors('role_id');
    }

    public function test_admin_cannot_lock_themself_out(): void
    {
        $this->actingAs($this->admin)->post('/admin/users/bulk', ['ids' => [$this->admin->id], 'action' => 'delete']);
        $this->assertModelExists($this->admin);
    }

    public function test_inactive_user_cannot_log_in_and_active_session_ends(): void
    {
        $this->admin->update(['is_active' => false]);

        $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->get('/admin')->assertRedirect(route('login'));
    }

    public function test_comment_moderation_honeypot_blocklist_and_escaping(): void
    {
        Setting::write(['comments_moderation' => '1', 'comments_blocklist' => 'casino']);
        $post = $this->article(['slug' => 'c']);
        $payload = ['author_name' => 'Budi', 'author_email' => 'b@example.test'];

        $this->post('/blog/c/comments', $payload + ['content' => 'first', 'website_confirm' => 'bot']);
        $this->assertSame(0, Comment::count());

        $this->post('/blog/c/comments', $payload + ['content' => '<script>x</script> nice']);
        $comment = Comment::firstOrFail();
        $this->assertSame('pending', $comment->status);
        $this->get('/blog/c')->assertDontSee('nice');

        $comment->update(['status' => 'approved']);
        $this->get('/blog/c')->assertSee('&lt;script&gt;x&lt;/script&gt; nice', false);

        $this->post('/blog/c/comments', $payload + ['content' => 'cheap Casino now']);
        $this->assertSame('spam', Comment::latest('id')->first()->status);
        $this->assertNotNull($post);
    }

    public function test_drafts_and_scheduled_posts_are_hidden_from_visitors(): void
    {
        $this->article(['slug' => 'draft', 'status' => 'draft']);
        $this->article(['slug' => 'later', 'published_at' => now()->addDay()]);

        $this->get('/blog/draft')->assertNotFound();
        $this->get('/blog/later')->assertNotFound();
        $this->actingAs($this->admin)->get('/blog/draft')->assertOk();
    }

    public function test_seo_output_sitemap_robots_and_feed(): void
    {
        $this->article(['slug' => 'seo', 'title' => 'SEO Post', 'meta_title' => 'Custom Title', 'meta_description' => 'Custom description']);
        $this->article(['slug' => 'hidden-one', 'noindex' => true]);

        $this->get('/blog/seo')
            ->assertSee('<title>Custom Title</title>', false)
            ->assertSee('content="Custom description"', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('application/ld+json', false);
        $this->get('/blog/hidden-one')->assertSee('name="robots" content="noindex"', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee('blog/seo')->assertDontSee('hidden-one');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin');
        $this->get('/feed')->assertOk()->assertSee('SEO Post');
    }

    public function test_page_renders_landing_with_page_seo_and_assets_are_contained(): void
    {
        Post::create(['type' => 'page', 'title' => 'Promo', 'slug' => 'promo', 'status' => 'published', 'published_at' => now()->subDay(), 'landing' => 'example', 'meta_title' => 'Promo SEO']);

        $this->get('/promo')->assertOk()->assertSee('<title>Promo SEO</title>', false)->assertSee('_landing/example/assets/style.css', false);
        $this->get('/_landing/example/assets/style.css')->assertOk();
        $this->get('/_landing/example/index.blade.php')->assertNotFound();
        $this->get('/_landing/example/landing.json')->assertNotFound();
        $this->get('/_landing/example/../../.env')->assertNotFound();
        $this->get('/_landing/example/..%2f..%2fcomposer.json')->assertNotFound();
    }

    public function test_page_set_as_homepage_lives_at_root(): void
    {
        $page = Post::create(['type' => 'page', 'title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay(), 'landing' => 'example']);
        Setting::write(['home_type' => 'page', 'home_page_id' => (string) $page->id]);

        $this->get('/')->assertOk()->assertSee('_landing/example', false);
        $this->get('/home')->assertRedirect('/');
    }

    public function test_upload_blocks_executable_files_even_when_type_is_allowed_in_settings(): void
    {
        Setting::write(['media_allowed' => 'jpg,php,svg,html']);
        $this->actingAs($this->admin);

        foreach (['shell.php' => '<?php echo 1;', 'x.svg' => '<svg onload="x()"/>', 'x.html' => '<script>1</script>'] as $name => $body) {
            $path = $this->storage.'/'.$name;
            file_put_contents($path, $body);
            $file = new \Illuminate\Http\UploadedFile($path, $name, null, null, true);
            $this->post('/admin/media', ['files' => [$file]])->assertSessionHas('error');
        }
        // A script disguised with an image extension is rejected by its content.
        $path = $this->storage.'/fake.jpg';
        file_put_contents($path, '<?php echo 1;');
        $this->post('/admin/media', ['files' => [new \Illuminate\Http\UploadedFile($path, 'fake.jpg', null, null, true)]])->assertSessionHas('error');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_settings_encrypt_mail_password_and_keep_it_when_left_empty(): void
    {
        $this->actingAs($this->admin);
        $base = ['mail_source' => 'settings', 'mail_scheme' => 'smtp'];

        $this->post('/admin/settings/email', $base + ['mail_password' => 'hunter2'])->assertSessionHas('success');
        $stored = Setting::read('mail_password');
        $this->assertNotSame('hunter2', $stored);
        $this->assertSame('hunter2', decrypt($stored));

        $this->post('/admin/settings/email', $base + ['mail_password' => '']);
        $this->assertSame('hunter2', decrypt(Setting::read('mail_password')));
    }

    public function test_backup_round_trip_restores_data(): void
    {
        $post = $this->article(['title' => 'Keep me']);
        $name = BackupService::create(false);

        $post->update(['title' => 'Changed']);
        $this->article(['title' => 'Added later']);

        BackupService::restore(BackupService::path($name));

        $this->assertSame('Keep me', Post::find($post->id)->title);
        $this->assertSame(0, Post::where('title', 'Added later')->count());
        $this->assertTrue(Hash::check('password123', User::find($this->admin->id)->password));
    }

    public function test_backup_routes_only_reach_listed_files(): void
    {
        $this->actingAs($this->admin)->get('/admin/settings/backups/..%2F..%2F.env/download')->assertNotFound();
    }

    public function test_export_and_import_round_trip(): void
    {
        $category = Term::create(['taxonomy' => 'category', 'name' => 'News', 'slug' => 'news']);
        $post = $this->article(['slug' => 'portable', 'title' => 'Portable']);
        $post->terms()->attach($category);
        $this->actingAs($this->admin);

        $json = $this->post('/admin/settings/tools/export', ['include' => ['articles']])->streamedContent();
        $post->delete();
        $category->delete();

        $path = $this->storage.'/import.json';
        file_put_contents($path, $json);
        $file = new \Illuminate\Http\UploadedFile($path, 'import.json', 'application/json', null, true);
        $this->post('/admin/settings/tools/import', ['file' => $file])->assertSessionHas('success');

        $restored = Post::where('slug', 'portable')->firstOrFail();
        $this->assertSame('news', $restored->categories()->value('slug'));
    }

    public function test_csrf_is_enforced_on_real_requests(): void
    {
        $middleware = app(\Illuminate\Foundation\Http\Kernel::class)->getMiddlewareGroups()['web'];
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, $middleware);
    }

    public function test_robots_is_not_hidden_by_a_static_file(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_homepage_has_one_h1_and_sitemap_lists_the_blog(): void
    {
        $response = $this->get('/')->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response->assertSee('<h1 class="site-title">Test Site</h1>', false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.url('blog').'</loc>', false);
    }

    public function test_list_pages_have_their_own_canonical_and_title_and_end_with_404(): void
    {
        Setting::write(['posts_per_page' => '1']);
        $this->article();
        $this->article();
        $term = Term::create(['taxonomy' => 'category', 'name' => 'News', 'slug' => 'news']);
        Post::query()->get()->each(fn ($post) => $post->terms()->attach($term));

        $this->get('/blog')->assertSee('<link rel="canonical" href="'.url('blog').'">', false);
        $this->get('/blog?page=2')
            ->assertSee('<link rel="canonical" href="'.url('blog').'?page=2">', false)
            ->assertSee('Page 2</title>', false);
        $this->get('/?page=2')->assertSee('<link rel="canonical" href="'.url('/').'/?page=2">', false);
        $this->get('/category/news?page=2')->assertSee('<link rel="canonical" href="'.url('category/news').'?page=2">', false);

        $this->get('/blog?page=3')->assertNotFound();
        $this->get('/category/news?page=3')->assertNotFound();
        $this->get('/search?q=Hello&page=3')->assertNotFound();
    }

    public function test_generated_descriptions_keep_words_apart_and_are_not_repeated(): void
    {
        $this->assertSame('Sub & title Body text.', cms_excerpt('<h2>Sub &amp; title</h2><p>Body <em>text</em>.</p>'));

        $this->article(['slug' => 'auto', 'content' => '<h2>Sub</h2><p>Isi artikel</p>']);
        $this->get('/blog/auto')->assertSee('<meta name="description" content="Sub Isi artikel">', false);

        Setting::write(['seo_description' => 'Site wide text']);
        $tag = Term::create(['taxonomy' => 'tag', 'name' => 'SEO', 'slug' => 'seo']);
        $this->get('/tag/'.$tag->slug)->assertSee('Articles about SEO on Test Site.')->assertDontSee('Site wide text');
        $this->get('/blog')->assertSee('All articles on Test Site.')->assertDontSee('Site wide text');
        $this->get('/search?q=x')->assertDontSee('Site wide text');
    }

    public function test_images_get_size_and_alt_from_the_media_library(): void
    {
        Media::create(['path' => 'uploads/2026/11/a.jpg', 'filename' => 'a.jpg', 'width' => 800, 'height' => 600, 'alt' => 'A "photo"']);
        $this->article(['slug' => 'pic', 'featured_image' => 'uploads/2026/11/a.jpg', 'content' => '<p><img src="/sub/uploads/2026/11/a.jpg" alt=""> <img src="https://other.test/b.jpg" alt="b > c"></p>']);

        $this->get('/blog/pic')
            ->assertSee('src="/sub/uploads/2026/11/a.jpg" alt="A &quot;photo&quot;" width="800" height="600" loading="lazy"', false)
            ->assertSee('alt="b &gt; c" loading="lazy"', false)
            ->assertSee('class="post-cover"', false)
            ->assertSee('fetchpriority="high"  width="800" height="600"', false);
    }

    public function test_new_visitors_get_no_cookies_unless_the_page_has_a_form(): void
    {
        $this->article(['slug' => 'closed', 'allow_comments' => false]);
        $this->article(['slug' => 'open', 'allow_comments' => true]);
        Setting::write(['comments_enabled' => '1']);

        foreach (['/', '/blog', '/blog/closed', '/robots.txt', '/sitemap.xml'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertSame([], $response->headers->getCookies(), $url);
            $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'), $url);
            $this->assertStringContainsString('Cookie', (string) $response->headers->get('Vary'), $url);
        }
        $this->assertSame([], $this->get('/does-not-exist')->assertNotFound()->headers->getCookies());

        $form = $this->get('/blog/open')->assertOk()->assertSee('name="_token"', false);
        $this->assertNotSame([], $form->headers->getCookies());
        $this->assertNotSame([], $this->get('/admin/login')->assertOk()->headers->getCookies());

        // Logged-in staff keep private responses, because drafts can be visible to them.
        $this->withUnencryptedCookie(config('session.cookie'), 'abc');
        $this->assertStringNotContainsString('public', (string) $this->get('/blog')->headers->get('Cache-Control'));
    }

    public function test_language_switch_needs_no_token_and_crawlers_get_the_site_language(): void
    {
        $middleware = app(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->assertContains('locale/*', $middleware->getExcludedPaths());

        Setting::write(['locale' => 'id']);
        $this->withHeader('Accept-Language', 'en')->get('/')->assertSee('<html lang="id">', false);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_comment_errors_are_shown_next_to_their_fields(): void
    {
        $this->article(['slug' => 'open', 'allow_comments' => true]);

        $this->from('/blog/open')->followingRedirects()->post('/blog/open/comments', ['author_name' => '', 'author_email' => 'a@b.test', 'content' => 'Halo'])
            ->assertSee('aria-describedby="error-author_name"', false)
            ->assertSee('id="error-author_name"', false)
            ->assertDontSee('id="error-content"', false);
    }

    public function test_users_and_roles_are_separate_sidebar_pages_outside_settings(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/users')->assertOk()->assertSee('<h1 class="page-title">Users</h1>', false)->assertDontSee('class="tabs"', false);
        $this->get('/admin/roles')->assertOk()->assertSee('<h1 class="page-title">Roles</h1>', false);
        $this->get('/admin/settings/users')->assertNotFound();

        $this->get('/admin')
            ->assertSee('href="'.route('admin.users.index').'"', false)
            ->assertSee('href="'.route('admin.roles.index').'"', false)
            ->assertSee('<div class="nav-label">Content</div>', false)
            ->assertSee('<div class="nav-label">Access</div>', false)
            ->assertDontSee('admin/landing');
        $this->get('/admin/landing')->assertNotFound();

        $cards = collect(AdminMenu::settingsCards())->pluck('title')->all();
        $this->assertSame(['General', 'Contact', 'Email', 'SEO', 'Media', 'Comments', 'Pages', 'Plugins', 'Activity log', 'Backup', 'Import & Export', 'Update'], $cards);
        $this->get('/admin/settings')->assertOk()->assertSeeInOrder(['General', 'Contact', 'Email', 'SEO'], false);
    }

    public function test_every_page_has_a_breadcrumb_and_no_back_link(): void
    {
        $this->actingAs($this->admin);
        $post = $this->article(['title' => 'Hello']);

        $crumbs = fn (string $url) => collect(explode('</li>', str($this->get($url)->assertOk()->getContent())->between('<ol>', '</ol>')))
            ->map(fn ($item) => trim(strip_tags($item)))->filter()->values()->all();

        $this->assertSame(['Dashboard'], $crumbs('/admin'));
        $this->assertSame(['Dashboard', 'Articles'], $crumbs('/admin/articles'));
        $this->assertSame(['Dashboard', 'Articles', 'New article'], $crumbs('/admin/articles/create'));
        $this->assertSame(['Dashboard', 'Articles', 'Edit: Hello'], $crumbs("/admin/articles/{$post->id}/edit"));
        $this->assertSame(['Dashboard', 'Users'], $crumbs('/admin/users'));
        $this->assertSame(['Dashboard', 'Settings'], $crumbs('/admin/settings'));
        $this->assertSame(['Dashboard', 'Settings', 'General'], $crumbs('/admin/settings/general'));
        $this->assertSame(['Dashboard', 'Settings', 'Backup'], $crumbs('/admin/settings/backups'));
        $this->assertSame(['Dashboard', 'Settings', 'Update'], $crumbs('/admin/settings/update'));

        foreach (['/admin/articles/create', '/admin/settings/general', '/admin/settings/backups', '/admin/settings/tools', '/admin/settings/update', '/admin/users'] as $url) {
            $this->get($url)->assertDontSee('←');
        }
    }

    public function test_global_search_finds_menus_and_content_the_user_may_open(): void
    {
        $this->article(['title' => 'Kopi pagi', 'user_id' => $this->admin->id]);
        Post::create(['type' => 'page', 'title' => 'Kopi halaman', 'slug' => 'kopi', 'status' => 'published', 'user_id' => $this->admin->id]);

        $labels = fn (array $groups) => collect($groups)->pluck('items')->flatten(1)->pluck('label')->all();

        $this->actingAs($this->admin);
        $all = $labels($this->getJson('/admin/search')->assertOk()->json('groups'));
        $this->assertContains('Users', $all);
        $this->assertContains('General', $all);
        $this->assertSame(['Backup'], $labels($this->getJson('/admin/search?q=BACK')->json('groups')));
        $found = $labels($this->getJson('/admin/search?q=kopi')->json('groups'));
        $this->assertSame(['Kopi pagi', 'Kopi halaman'], $found);
        $this->assertSame([], $this->getJson('/admin/search?q=tidak-ada')->json('groups'));
        $this->assertSame([], $this->getJson('/admin/search?q=%25%25')->json('groups'));

        $authorRole = Role::create(['name' => 'Author', 'slug' => 'author', 'capabilities' => ['articles.write']]);
        $author = User::create(['role_id' => $authorRole->id, 'name' => 'A', 'email' => 'a@example.test', 'password' => 'password123']);
        $this->actingAs($author);
        $this->assertSame([], $this->getJson('/admin/search?q=kopi')->json('groups'));
        $this->article(['title' => 'Kopi saya', 'user_id' => $author->id]);
        $this->assertSame(['Kopi saya'], $labels($this->getJson('/admin/search?q=kopi')->json('groups')));
        $this->assertNotContains('Users', $labels($this->getJson('/admin/search')->json('groups')));
    }

    public function test_every_admin_screen_renders_in_both_languages(): void
    {
        $this->actingAs($this->admin);
        $urls = ['/admin', '/admin/articles', '/admin/articles/create', '/admin/pages', '/admin/pages/create', '/admin/categories', '/admin/categories/create', '/admin/tags', '/admin/comments', '/admin/media', '/admin/media/upload', '/admin/media/picker', '/admin/settings', '/admin/users', '/admin/users/create', '/admin/roles', '/admin/roles/create', '/admin/settings/logs', '/admin/settings/backups', '/admin/settings/tools', '/admin/settings/update', '/admin/settings/plugins', '/admin/settings/plugins/hello-bar/settings', '/admin/settings/general', '/admin/settings/contact', '/admin/settings/email', '/admin/settings/seo', '/admin/settings/media', '/admin/settings/comments', '/admin/settings/pages'];

        foreach (['en', 'id'] as $locale) {
            $this->withSession(['locale' => $locale]);
            foreach ($urls as $url) {
                $this->get($url)->assertOk();
            }
        }
        $this->withSession(['locale' => 'id'])->get('/admin/articles')->assertSee('Artikel baru');
    }
}
