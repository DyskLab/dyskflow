<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LandingHomeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/views');
        $this->app->useStoragePath($storage);
        file_put_contents($storage.'/app/.installed', 'test');

        $this->admin = User::create(['role_id' => Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true])->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        Setting::write(['locale' => 'en', 'site_title' => 'Test Site', 'site_url' => 'http://localhost']);
    }

    public function test_the_default_homepage_is_the_dyskflow_landing_in_both_languages(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('_landing/dyskflow/assets/style.css', false)
            ->assertSee('hreflang="id"', false);
        $this->assertEmpty($response->headers->getCookies());
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));

        $this->get('/id')->assertOk()->assertSee('Pasang Laravel', false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.url('/').'</loc>', false)->assertSee(url('id'), false);
    }

    public function test_landing_assets_change_address_when_the_file_changes(): void
    {
        $version = filemtime(base_path('landing/dyskflow/assets/style.css'));

        $this->get('/')->assertSee('_landing/dyskflow/assets/style.css?v='.$version, false);
    }

    public function test_the_homepage_can_use_another_landing_or_something_else(): void
    {
        Setting::write(['home_landing' => 'example']);
        $this->get('/')->assertSee('_landing/example/assets/style.css', false);

        Setting::write(['home_landing' => 'missing-folder']);
        $this->get('/')->assertOk()->assertDontSee('_landing/', false)->assertSee('post-list', false);

        Setting::write(['home_type' => 'posts', 'home_landing' => 'dyskflow']);
        $this->get('/')->assertOk()->assertDontSee('_landing/', false);
    }

    public function test_a_page_cannot_be_chosen_as_a_landing_page(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/pages/create')->assertOk()->assertDontSee('name="landing"', false);

        $this->post('/admin/pages', ['title' => 'Promo', 'status' => 'published', 'content' => '<p>Own words</p>', 'landing' => 'dyskflow'])->assertRedirect();
        $this->assertNull(Post::where('slug', 'promo')->value('landing'));

        auth()->logout();
        $this->get('/promo')->assertOk()->assertSee('Own words')->assertDontSee('_landing/', false);
    }

    public function test_only_a_public_landing_has_its_own_address(): void
    {
        $this->get('/example')->assertNotFound();
        $this->get('/dyskflow')->assertRedirect(url('/'));

        $slug = 'zz-test-'.bin2hex(random_bytes(3));
        $folder = base_path('landing/'.$slug);
        File::ensureDirectoryExists($folder);
        try {
            file_put_contents($folder.'/landing.json', '{"title":"Promo title","description":"Promo text","public":true}');
            file_put_contents($folder.'/index.html', '<!doctype html><html><head></head><body>Promo body</body></html>');

            $this->get('/'.$slug)->assertOk()->assertSee('Promo body')->assertSee('Promo title')->assertSee('Promo text');
            $this->get('/id/'.$slug)->assertOk()->assertSee('Promo body');
            $this->get('/sitemap.xml')->assertSee(url($slug), false);

            // A page with the same slug comes first.
            Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'Real', 'slug' => $slug, 'status' => 'published', 'content' => '<p>Real page</p>']);
            $this->get('/'.$slug)->assertSee('Real page')->assertDontSee('Promo body');
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_the_contact_page_reads_the_settings_and_sends_no_cookies(): void
    {
        $this->get('/kontak')->assertOk()->assertSee('Our contact details will be here soon.')->assertDontSee('contact-card', false);

        Setting::write(['contact_email' => 'halo@example.test', 'contact_whatsapp' => '62 812-3456', 'contact_address' => 'Jalan Merdeka 1', 'contact_map_url' => 'https://maps.example.test/x', 'social_x' => 'https://x.example.test/me', 'social_tiktok' => 'javascript:alert(1)']);

        $response = $this->get('/kontak')->assertOk()
            ->assertSee('mailto:halo@example.test', false)
            ->assertSee('https://wa.me/628123456', false)
            ->assertSee('Jalan Merdeka 1')
            ->assertSee('https://maps.example.test/x', false)
            ->assertSee('https://x.example.test/me', false)
            ->assertDontSee('javascript:alert', false)
            ->assertSee('<title>Contact', false);
        $this->assertEmpty($response->headers->getCookies());
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));

        $this->get('/id/kontak')->assertOk()->assertSee('<title>Kontak', false)->assertSee('Alamat');
    }

    public function test_the_contact_page_is_in_the_menu_unless_a_page_uses_its_slug(): void
    {
        $this->get('/blog')->assertSee('<a href="'.url('kontak').'">Contact</a>', false);
        $this->get('/id/blog')->assertSee('<a href="'.url('id/kontak').'">Kontak</a>', false);

        Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'Mine', 'slug' => 'kontak', 'status' => 'published', 'content' => '<p>My words</p>']);

        $this->get('/blog')->assertDontSee('<a href="'.url('kontak').'">', false);
        $this->get('/kontak')->assertSee('My words');
    }

    public function test_blog_and_landing_share_one_header(): void
    {
        Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'Contact', 'slug' => 'contact', 'status' => 'published', 'show_in_menu' => true]);
        $header = fn (string $url) => preg_replace('/\s+/', ' ', str($this->get($url)->getContent())->between('<header class="site-header">', '</header>')->toString());

        $landing = $header('/');

        $this->assertStringContainsString('Contact', $landing);
        $this->assertStringContainsString('data-theme-toggle', $landing);
        $this->assertStringContainsString('role="search"', $landing);
        // The language link points at each page's own version, so only that differs.
        $strip = fn (string $html) => preg_replace('/\s+/', ' ', preg_replace('/<a class="btn btn-ghost btn-sm"[^>]*hreflang.*?<\/a>/', '', $html));
        $this->assertSame($strip($landing), $strip($header('/blog')));
    }

    public function test_the_migration_moves_an_attached_homepage_to_its_folder(): void
    {
        $migration = require database_path('migrations/2026_11_06_000200_move_landing_out_of_pages.php');
        $home = Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'About', 'slug' => 'about', 'status' => 'published', 'landing' => 'dyskflow']);
        $full = Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'Full', 'slug' => 'full', 'status' => 'published', 'landing' => 'example', 'content' => '<p>Words</p>']);
        $plain = Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'Plain', 'slug' => 'plain', 'status' => 'published']);
        Setting::write(['home_type' => 'page', 'home_page_id' => (string) $home->id]);

        $migration->up();

        $value = fn (string $key) => DB::table('settings')->where('key', $key)->value('value');
        $this->assertSame('landing', $value('home_type'));
        $this->assertSame('dyskflow', $value('home_landing'));
        $this->assertSoftDeleted($home);
        $this->assertNotSoftDeleted($full);
        $this->assertNotSoftDeleted($plain);
    }

    public function test_the_migration_keeps_the_article_list_for_an_install_that_never_chose(): void
    {
        $migration = require database_path('migrations/2026_11_06_000200_move_landing_out_of_pages.php');

        $migration->up();

        $this->assertSame('posts', DB::table('settings')->where('key', 'home_type')->value('value'));
    }
}
