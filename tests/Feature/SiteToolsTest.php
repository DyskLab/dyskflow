<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SiteToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/views');
        $this->app->useStoragePath($storage);
        file_put_contents($storage.'/app/.installed', 'test');

        $this->admin = User::create(['role_id' => Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true])->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        $this->author = User::create(['role_id' => Role::create(['name' => 'Author', 'slug' => 'author', 'capabilities' => ['articles.write']])->id, 'name' => 'Author', 'email' => 'author@example.test', 'password' => 'password123']);
        Setting::write(['locale' => 'en', 'site_title' => 'Test Site', 'contact_email' => 'hello@example.test']);
    }

    private function check(string $title): array
    {
        $this->actingAs($this->admin);
        $this->get('/admin/settings/health');

        return collect(SiteHealth::checks())->firstWhere('title', $title);
    }

    public function test_the_logo_is_the_default_favicon_until_one_is_chosen(): void
    {
        $this->assertStringStartsWith("\0\0\1\0", file_get_contents(public_path('favicon.ico')));

        $this->get('/admin/login')->assertSee('img/logo-mark.svg', false);
        $this->get('/blog')->assertSee('favicon.ico', false)->assertSee('img/logo-mark.svg', false);

        Setting::write(['favicon' => 'uploads/mine.png']);

        $this->get('/blog')->assertSee('uploads/mine.png', false)->assertDontSee('favicon.ico', false);
        $this->get('/admin/login')->assertSee('uploads/mine.png', false)->assertDontSee('favicon.ico', false);
    }

    public function test_both_pages_need_the_settings_capability_and_appear_as_cards(): void
    {
        $this->actingAs($this->author)->get('/admin/settings/health')->assertForbidden();
        $this->actingAs($this->author)->get('/admin/settings/privacy')->assertForbidden();
        $this->actingAs($this->author)->post('/admin/settings/privacy/draft')->assertForbidden();

        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()->assertSee('Privacy guide')->assertSee('Site health');
        $this->get('/admin/settings/health')->assertOk()->assertSee('PHP version');
        $this->get('/admin/settings/privacy')->assertOk()->assertSee('What this site collects');
    }

    public function test_the_guide_follows_the_real_settings(): void
    {
        $this->actingAs($this->admin)->get('/admin/settings/privacy')->assertSee('People who comment give a name');

        Setting::write(['comments_enabled' => '0', 'mail_source' => 'settings', 'mail_host' => 'smtp.example.test']);

        $this->get('/admin/settings/privacy')->assertDontSee('People who comment give a name')->assertSee('smtp.example.test');
    }

    public function test_a_draft_is_made_once_and_stays_private_until_published(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings/privacy/draft')->assertRedirect();
        $this->post('/admin/settings/privacy/draft')->assertRedirect();

        $page = Post::where('slug', 'privacy-policy')->sole();
        $this->assertSame('draft', $page->status);
        $this->assertStringContainsString('hello@example.test', $page->content);
        $this->assertSame((string) $page->id, (string) Setting::where('key', 'privacy_page_id')->value('value'));

        auth()->logout();
        $this->get('/blog')->assertOk()->assertDontSee('Privacy Policy');

        $page->update(['status' => 'published']);
        $this->get('/blog')->assertOk()->assertSee('Privacy Policy')->assertSee(url('privacy-policy'), false);
    }

    public function test_the_draft_slug_avoids_an_existing_page(): void
    {
        Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'Mine', 'slug' => 'privacy-policy', 'status' => 'published']);

        $this->actingAs($this->admin)->post('/admin/settings/privacy/draft');

        $this->assertSame(1, Post::where('slug', 'privacy-policy-2')->count());
    }

    public function test_health_reports_real_problems(): void
    {
        Config::set('app.debug', true);
        $this->assertSame(SiteHealth::CRITICAL, $this->check('Debug mode')['status']);
        Config::set('app.debug', false);
        $this->assertSame(SiteHealth::GOOD, $this->check('Debug mode')['status']);

        $this->assertSame(SiteHealth::WARNING, $this->check('Two-step verification')['status']);
        User::query()->update(['totp_confirmed_at' => now()]);
        $this->assertSame(SiteHealth::GOOD, $this->check('Two-step verification')['status']);

        $this->assertSame(SiteHealth::WARNING, $this->check('Backup')['status']);

        Setting::write(['seo_noindex' => '1']);
        $this->assertSame(SiteHealth::WARNING, $this->check('Search engines')['status']);
    }

    public function test_health_finds_a_migration_that_has_not_run(): void
    {
        $this->assertSame(SiteHealth::GOOD, $this->check('Database tables')['status']);

        DB::table('migrations')->where('migration', 'like', '%add_totp%')->delete();

        $check = $this->check('Database tables');
        $this->assertSame(SiteHealth::CRITICAL, $check['status']);
        $this->assertSame(route('admin.settings.update.finish'), $check['action']['url']);
    }

    public function test_results_are_sorted_worst_first_and_show_no_secrets(): void
    {
        Config::set('app.debug', true);
        Setting::write(['mail_password' => 'super-secret-value']);

        $this->actingAs($this->admin)->get('/admin/settings/health')->assertOk()->assertDontSee('super-secret-value')->assertDontSee((string) config('app.key'));

        $statuses = array_column(SiteHealth::checks(), 'status');
        $this->assertSame(SiteHealth::CRITICAL, $statuses[0]);
        $this->assertSame(SiteHealth::GOOD, end($statuses));
    }
}
