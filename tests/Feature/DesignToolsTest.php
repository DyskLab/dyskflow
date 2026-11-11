<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Brand;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DesignToolsTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/views');
        $this->app->useStoragePath($storage);
        file_put_contents($storage.'/app/.installed', 'test');

        $this->base = sys_get_temp_dir().'/cms-design-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->base.'/theme');
        File::ensureDirectoryExists($this->base.'/plugins');
        config(['cms.design_roots' => ['theme' => $this->base.'/theme', 'landing' => base_path('landing'), 'plugins' => $this->base.'/plugins']]);
        Theme::apply();

        $this->admin = $this->user(['*'], 'admin');
        Setting::write(['locale' => 'en', 'site_title' => 'Test Site', 'site_url' => 'http://localhost']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function user(array $capabilities, string $slug): User
    {
        $role = Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'capabilities' => $capabilities, 'is_system' => $slug === 'admin']);

        return User::create(['role_id' => $role->id, 'name' => ucfirst($slug), 'email' => "$slug@example.test", 'password' => 'password123']);
    }

    public function test_administrators_see_the_design_group_and_its_pages(): void
    {
        // The code tools stay out of the sidebar until the developer menu is turned on; their pages still open.
        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee(route('admin.design.templates.index'), false)
            ->assertDontSee(route('admin.design.files.index'), false)
            ->assertDontSee(__('Developer'));

        // One Explorer item holds the landing pages, the theme and the plugins.
        Setting::write(['developer_menu' => '1']);
        $this->get('/admin')->assertOk()
            ->assertSee(__('Developer'))
            ->assertSee(__('Explorer'))
            ->assertSee(route('admin.design.files.index'), false);
        $this->getJson('/admin/search?q='.urlencode(__('Theme')))->assertOk()->assertJsonFragment(['label' => __('Explorer'), 'url' => route('admin.design.files.index')]);

        $this->get('/admin/design/files')->assertOk()->assertSee('theme/')->assertSee('landing/')->assertSee(__('Custom CSS'))->assertSee(__('Custom JavaScript'));
        $this->get('/admin/design/files?dir=landing')->assertOk()->assertSee('dyskflow/');
        $this->get('/admin/design/files?dir=theme')->assertOk()->assertSee(route('admin.design.theme.index'), false);
        $this->get('/admin/design/theme')->assertOk()->assertSee('site/layout.blade.php')->assertSee('css/site.css');
        $this->get('/admin/design/landing')->assertRedirect(route('admin.design.files.index', ['dir' => 'landing']));
        $this->get('/admin/roles/create')->assertOk()->assertSee('Edit code (PHP and Blade)');
    }

    public function test_people_without_the_design_permission_cannot_open_it(): void
    {
        $editor = $this->user(['articles.manage', 'pages.manage'], 'editor');

        $this->actingAs($editor)->get('/admin')->assertOk()->assertDontSee(route('admin.design.files.index'), false);
        $this->get('/admin/design/files')->assertForbidden();
        $this->get('/admin/design/theme')->assertForbidden();
        $this->post('/admin/design/theme/override', ['path' => 'css/site.css'])->assertForbidden();
    }

    public function test_templates_need_the_code_permission(): void
    {
        $designer = $this->user(['appearance.manage'], 'designer');

        $this->actingAs($designer)->get('/admin/design/theme')->assertOk()
            ->assertSee('data-post-fields="'.e(json_encode(['path' => 'css/site.css'])).'"', false)
            ->assertDontSee('data-post-fields="'.e(json_encode(['path' => 'site/layout.blade.php'])).'"', false);

        $this->post('/admin/design/theme/override', ['path' => 'site/layout.blade.php'])->assertForbidden();
        $this->assertFileDoesNotExist($this->base.'/theme/views/site/layout.blade.php');

        $this->post('/admin/design/theme/override', ['path' => 'css/site.css'])->assertRedirect(route('admin.design.files.edit', ['path' => 'theme/assets/css/site.css']));
        $this->assertFileExists($this->base.'/theme/assets/css/site.css');
    }

    public function test_the_code_editor_switch_blocks_templates_even_for_administrators(): void
    {
        config(['cms.code_editor' => false]);

        $this->actingAs($this->admin)->post('/admin/design/theme/override', ['path' => 'site/layout.blade.php'])->assertForbidden();
        $this->assertFileDoesNotExist($this->base.'/theme/views/site/layout.blade.php');
    }

    public function test_override_and_reset_are_logged(): void
    {
        $this->actingAs($this->admin)->post('/admin/design/theme/override', ['path' => 'site/partials/footer.blade.php'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertFileExists($this->base.'/theme/views/site/partials/footer.blade.php');
        $this->get('/admin/design/theme?f[status]=overridden')->assertOk()->assertSee('site/partials/footer.blade.php')->assertDontSee('site/layout.blade.php');

        $this->post('/admin/design/theme/bulk', ['action' => 'reset', 'ids' => ['site/partials/footer.blade.php', '../../.env']])
            ->assertRedirect()->assertSessionHas('success', 'Done: 1 item(s).');
        $this->assertFileDoesNotExist($this->base.'/theme/views/site/partials/footer.blade.php');

        $this->assertSame(['theme.overridden', 'theme.reset'], ActivityLog::orderBy('id')->pluck('action')->all());
        $this->post('/admin/design/theme/override', ['path' => '../.env'])->assertNotFound();
    }

    public function test_colors_and_fonts_lights_only_its_own_sidebar_item(): void
    {
        $settings = 'class="nav-item active" href="'.route('admin.settings.index').'"';

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'design'))->assertOk()
            ->assertSee('class="nav-item active" href="'.route('admin.settings.edit', 'design').'"', false)
            ->assertDontSee($settings, false);
        $this->get(route('admin.settings.edit', 'general'))->assertOk()->assertSee($settings, false);
    }

    public function test_global_colors_and_fonts_reach_every_public_page(): void
    {
        $this->get('/')->assertOk()->assertDontSee('cms-brand', false);
        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'design'))->assertOk()->assertSee('data-color-field', false);
        $this->get(route('admin.dashboard'))->assertSee(route('admin.settings.edit', 'design'), false);

        $this->post(route('admin.settings.update', 'design'), ['brand_primary' => 'red'])->assertSessionHasErrors('brand_primary');

        Http::fake([
            'fonts.googleapis.com/css2?family=Lora*' => Http::response('', 500),
            'fonts.googleapis.com/*' => Http::response("/* cyrillic */\n@font-face { font-family: 'Poppins'; src: url(https://fonts.gstatic.com/s/poppins/cyr.woff2) format('woff2'); }\n/* latin */\n@font-face { font-family: 'Poppins'; font-weight: 400; src: url(https://fonts.gstatic.com/s/poppins/latin.woff2) format('woff2'); }"),
            'fonts.gstatic.com/*' => Http::response('wOF2'),
        ]);
        $dir = Brand::fontDir('Poppins');
        File::deleteDirectory($dir);
        try {
            $this->post(route('admin.settings.update', 'design'), [
                'brand_primary' => '#FFD000', 'brand_secondary' => '#123', 'brand_accent' => '',
                'brand_font_heading' => 'Poppins', 'brand_font_body' => 'serif', 'brand_font_size' => '18', 'brand_radius' => 'lg',
            ])->assertSessionHasNoErrors()->assertSessionMissing('error');

            // Only the Latin subset is kept, and served from this site.
            $this->assertCount(1, glob($dir.'/*.woff2'));
            Http::assertNotSent(fn ($request) => str_contains($request->url(), 'cyr.woff2'));

            foreach (['/', '/kontak'] as $url) {
                $html = $this->get($url)->assertOk()->getContent();
                $this->assertSame(1, substr_count($html, 'id="cms-brand"'), $url);
                $this->assertStringContainsString('--primary:#ffd000', $html);
                $this->assertStringContainsString('--primary-foreground:#111111', $html, 'dark text on a light yellow');
                $this->assertStringContainsString('--brand-secondary:#112233', $html);
                $this->assertStringContainsString('--brand-secondary-foreground:#ffffff', $html);
                $this->assertStringContainsString('--radius:1rem', $html);
                $this->assertStringContainsString('"Poppins"', $html);
                $this->assertStringContainsString('/uploads/fonts/poppins/', $html);
                $this->assertStringNotContainsString('fonts.gstatic.com', $html);
                $this->assertStringContainsString('body{font-size:18px}', $html);
            }
        } finally {
            File::deleteDirectory($dir);
        }

        // Without the network the setting is saved and the problem is named.
        $this->post(route('admin.settings.update', 'design'), ['brand_font_heading' => 'Lora', 'brand_font_body' => ''])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Lora'));
        File::deleteDirectory(Brand::fontDir('Lora'));
        @rmdir(public_path('uploads/fonts'));
    }

    public function test_custom_css_and_javascript_load_on_every_public_page_once(): void
    {
        $this->actingAs($this->admin)->get(route('admin.design.files.custom', 'css'))
            ->assertRedirect(route('admin.design.files.edit', ['path' => 'theme/assets/css/custom.css']));
        $this->assertFileExists($this->base.'/theme/assets/css/custom.css');
        // An empty file adds nothing to the pages.
        $this->get('/')->assertOk()->assertDontSee('custom.css', false);

        File::put($this->base.'/theme/assets/css/custom.css', 'body{color:red}');
        File::ensureDirectoryExists($this->base.'/theme/assets/js');
        File::put($this->base.'/theme/assets/js/custom.js', 'console.log(1)');

        foreach (['/', '/kontak'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '_theme/css/custom.css?v='), $url);
            $this->assertSame(1, substr_count($html, '_theme/js/custom.js?v='), $url);
        }
        $html = $this->get('/')->getContent();
        $this->assertLessThan(strpos($html, '_theme/js/custom.js'), strpos($html, 'js/site.js'), 'after site.js');
        $this->get('/_theme/css/custom.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');

        config(['cms.theme_overrides' => false]);
        $this->get('/')->assertOk()->assertDontSee('custom.css', false)->assertDontSee('custom.js', false);
    }

    public function test_an_unwritable_folder_is_reported(): void
    {
        chmod($this->base.'/plugins', 0555);
        if (is_writable($this->base.'/plugins')) {
            chmod($this->base.'/plugins', 0755);
            $this->markTestSkipped('Running as a user who can write anywhere.');
        }

        $this->actingAs($this->admin)->get('/admin/design/files')->assertOk()->assertSee('The plugins/ folder is not writable.');
        chmod($this->base.'/plugins', 0755);
    }
}
