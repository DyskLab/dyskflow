<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\SafeFiles;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ThemeOverrideTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/views');
        $this->app->useStoragePath($storage);
        file_put_contents($storage.'/app/.installed', 'test');

        $this->base = sys_get_temp_dir().'/cms-theme-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->base.'/theme');
        config(['cms.design_roots' => ['theme' => $this->base.'/theme', 'landing' => base_path('landing'), 'plugins' => base_path('plugins')]]);
        Theme::apply();

        User::create(['role_id' => Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true])->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        Setting::write(['locale' => 'en', 'site_title' => 'Test Site', 'site_url' => 'http://localhost', 'home_type' => 'posts']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_a_site_view_in_the_theme_wins_over_the_core_view(): void
    {
        $this->assertSame('theme/views/site/partials/footer.blade.php', Theme::override('site/partials/footer.blade.php'));
        File::put($this->base.'/theme/views/site/partials/footer.blade.php', '<footer>THEME FOOTER</footer>');

        $this->assertSame(realpath($this->base.'/theme/views/site/partials/footer.blade.php'), realpath(view('site.partials.footer')->getPath()));
        $this->get('/')->assertOk()->assertSee('THEME FOOTER');
        $this->assertContains(['path' => 'site/partials/footer.blade.php', 'kind' => 'view', 'label' => 'site/partials/footer.blade.php', 'overridden' => true], Theme::files());
    }

    public function test_views_outside_site_cannot_be_overridden(): void
    {
        File::ensureDirectoryExists($this->base.'/theme/views/admin/partials');
        File::put($this->base.'/theme/views/admin/partials/head.blade.php', 'HIJACKED');

        $this->assertSame(realpath(resource_path('views/admin/partials/head.blade.php')), realpath(view('admin.partials.head')->getPath()));
        $this->get('/admin/login')->assertOk()->assertDontSee('HIJACKED');
    }

    public function test_overrides_can_be_switched_off_to_recover(): void
    {
        Theme::override('site/partials/footer.blade.php');
        File::put($this->base.'/theme/views/site/partials/footer.blade.php', '@php throw new Exception("broken"); @endphp');
        File::ensureDirectoryExists($this->base.'/theme/assets/css');
        File::put($this->base.'/theme/assets/css/site.css', 'body{}');

        config(['cms.theme_overrides' => false]);

        $this->get('/')->assertOk()->assertSee('css/site.css?v='.filemtime(public_path('css/site.css')), false)->assertDontSee('_theme/', false);
        $this->get('/_theme/css/site.css')->assertNotFound();
    }

    public function test_reset_removes_the_override_and_keeps_a_revision(): void
    {
        Theme::override('site/partials/footer.blade.php');
        File::put($this->base.'/theme/views/site/partials/footer.blade.php', '<footer>MINE</footer>');

        Theme::reset('site/partials/footer.blade.php');

        $this->assertFileDoesNotExist($this->base.'/theme/views/site/partials/footer.blade.php');
        $this->assertSame(realpath(resource_path('views/site/partials/footer.blade.php')), realpath(view('site.partials.footer')->getPath()));
        $this->assertSame('<footer>MINE</footer>', SafeFiles::revisions('theme/views/site/partials/footer.blade.php')->first()->content);
    }

    public function test_only_known_core_files_can_be_overridden(): void
    {
        foreach (['../.env', 'site/../../.env', 'app/Models/User.php', 'css/admin.css', 'site/missing.blade.php'] as $path) {
            try {
                Theme::override($path);
                $this->fail("$path should not be overridable.");
            } catch (HttpException $e) {
                $this->assertSame(404, $e->getStatusCode());
            }
        }
        $this->assertSame([], glob($this->base.'/theme/*'));
    }

    public function test_theme_assets_replace_the_core_assets_with_one_version_parameter(): void
    {
        // Core files carry their modification time, so a browser never keeps an old copy.
        $version = filemtime(public_path('css/site.css'));
        $this->assertSame(asset('css/site.css').'?v='.$version, theme_asset('css/site.css'));
        $this->get('/')->assertSee('css/site.css?v='.$version.'"', false);

        $this->assertSame('theme/assets/css/site.css', Theme::override('css/site.css'));
        $mtime = filemtime($this->base.'/theme/assets/css/site.css');

        $this->assertSame(url('_theme/css/site.css').'?v='.$mtime, theme_asset('css/site.css'));
        $this->get('/')->assertSee('_theme/css/site.css?v='.$mtime.'"', false)->assertSee('css/ui.css?v='.filemtime(public_path('css/ui.css')).'"', false);
        $this->get('/_theme/css/site.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    }

    public function test_theme_assets_are_contained(): void
    {
        File::ensureDirectoryExists($this->base.'/theme/assets');
        File::put($this->base.'/theme/assets/x.php', '<?php echo 1;');
        File::put($this->base.'/theme/secret.css', 'outside assets');

        $this->get('/_theme/x.php')->assertNotFound();
        $this->get('/_theme/../secret.css')->assertNotFound();
        $this->get('/_theme/..%2fsecret.css')->assertNotFound();
        $this->get('/_theme/missing.css')->assertNotFound();
    }

    public function test_the_theme_folder_survives_updates_and_its_url_is_reserved(): void
    {
        $this->assertContains('theme', config('cms.update_protected'));
        $this->assertContains('_theme', config('cms.reserved_slugs'));
        $this->assertContains('file_revisions', config('cms.backup_tables'));
        $this->assertContains('appearance.manage', config('cms.capabilities'));
        $this->assertContains('code.manage', config('cms.capabilities'));
    }
}
