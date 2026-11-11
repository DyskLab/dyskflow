<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\FileController;
use App\Models\ActivityLog;
use App\Models\FileRevision;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\BackupService;
use App\Support\SafeFiles;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class FileManagerTest extends TestCase
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

        $this->base = sys_get_temp_dir().'/cms-files-'.bin2hex(random_bytes(4));
        foreach (['theme/assets', 'theme/views/site', 'landing/promo/assets', 'plugins'] as $dir) {
            File::ensureDirectoryExists($this->base.'/'.$dir);
        }
        File::put($this->base.'/theme/assets/site.css', 'body { color: red; }');
        File::put($this->base.'/theme/views/site/footer.blade.php', '<footer>{{ 1 }}</footer>');
        File::put($this->base.'/landing/promo/landing.json', '{"title": "Promo"}');
        config(['cms.design_roots' => ['theme' => $this->base.'/theme', 'landing' => $this->base.'/landing', 'plugins' => $this->base.'/plugins']]);

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

    private function confirmed(): self
    {
        return $this->withSession(['design.code_confirmed_at' => time()]);
    }

    private function save(string $path, string $content, ?string $hash = null, bool $force = false)
    {
        return $this->postJson('/admin/design/files/save', ['path' => $path, 'content' => $content, 'hash' => $hash, 'force' => $force]);
    }

    public function test_folders_can_be_browsed_but_not_left(): void
    {
        $this->actingAs($this->admin)->get('/admin/design/files?dir=theme')->assertOk()
            ->assertSee('assets/')->assertSee('views/')
            ->assertSee(route('admin.design.files.create', ['dir' => 'theme', 'kind' => 'file']));
        $this->get('/admin/design/files?dir=theme/assets')->assertOk()->assertSee('site.css')->assertSee('All folders');

        $this->get('/admin/design/files?dir=../')->assertNotFound();
        $this->get('/admin/design/files?dir=theme/assets/site.css')->assertNotFound();
        $this->get('/admin/design/files/edit?path=theme/../../secret')->assertNotFound();
    }

    public function test_a_stylesheet_is_edited_and_saved_with_a_revision(): void
    {
        $this->actingAs($this->admin)->get('/admin/design/files/edit?path=theme/assets/site.css')->assertOk()
            ->assertSee('body { color: red; }')->assertSee('vendor/codemirror/lib/codemirror.min.js', false)
            ->assertSee('data-language="css"', false);

        $response = $this->save('theme/assets/site.css', 'body { color: blue; }', hash('sha256', 'body { color: red; }'))->assertOk();

        $this->assertSame(hash('sha256', 'body { color: blue; }'), $response->json('hash'));
        $this->assertSame('body { color: blue; }', File::get($this->base.'/theme/assets/site.css'));
        $this->assertSame('body { color: red; }', FileRevision::where('path', 'theme/assets/site.css')->value('content'));
        $this->assertSame('file.saved', ActivityLog::latest('id')->value('action'));
    }

    public function test_saved_content_keeps_its_whitespace(): void
    {
        $content = "\n  body { color: blue; }\n\n";
        $this->actingAs($this->admin)->save('theme/assets/site.css', $content)->assertOk();
        $this->assertSame($content, File::get($this->base.'/theme/assets/site.css'));

        $this->save('theme/assets/site.css', '')->assertOk();
        $this->assertSame('', File::get($this->base.'/theme/assets/site.css'));
    }

    public function test_a_stale_save_is_refused_until_overwrite_is_chosen(): void
    {
        $this->actingAs($this->admin);
        $stale = hash('sha256', 'something older');

        $this->save('theme/assets/site.css', 'mine', $stale)->assertStatus(409)->assertJson(['conflict' => true]);
        $this->assertSame('body { color: red; }', File::get($this->base.'/theme/assets/site.css'));

        $this->save('theme/assets/site.css', 'mine', $stale, true)->assertOk();
        $this->assertSame('mine', File::get($this->base.'/theme/assets/site.css'));
    }

    public function test_invalid_json_is_refused_with_a_message(): void
    {
        $this->actingAs($this->admin)->save('landing/promo/landing.json', '{"title": ')->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'not valid JSON'));
        $this->assertSame('{"title": "Promo"}', File::get($this->base.'/landing/promo/landing.json'));
    }

    public function test_templates_need_the_code_permission(): void
    {
        $designer = $this->user(['appearance.manage'], 'designer');

        $this->actingAs($designer)->get('/admin/design/files?dir=theme/views/site')->assertOk()
            ->assertDontSee(route('admin.design.files.edit', ['path' => 'theme/views/site/footer.blade.php']), false);
        $this->get('/admin/design/files/edit?path=theme/views/site/footer.blade.php')->assertForbidden();
        $this->save('theme/views/site/footer.blade.php', 'hacked')->assertForbidden();
        $this->post(route('admin.design.files.store', ['dir' => 'theme', 'kind' => 'file']), ['name' => 'x.php'])->assertForbidden();
        $this->post('/admin/design/files/delete', ['path' => 'theme/views/site/footer.blade.php'])->assertForbidden();
        $this->post(route('admin.design.files.rename.save', ['path' => 'theme/assets/site.css']), ['name' => 'site.php'])->assertForbidden();
        $this->post(route('admin.design.files.upload.save', ['dir' => 'theme/assets']), ['file' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')])->assertForbidden();

        $this->assertSame('<footer>{{ 1 }}</footer>', File::get($this->base.'/theme/views/site/footer.blade.php'));
    }

    public function test_code_needs_a_fresh_password_confirmation(): void
    {
        $edit = route('admin.design.files.edit', ['path' => 'theme/views/site/footer.blade.php']);

        $this->actingAs($this->admin)->get($edit)->assertRedirect(route('admin.design.confirm', ['return' => $edit]));
        $this->save('theme/views/site/footer.blade.php', 'x')->assertStatus(423)->assertJsonStructure(['message', 'confirm']);

        $this->get(route('admin.design.confirm', ['return' => $edit]))->assertOk()->assertSee('autocomplete="current-password"', false);
        $this->post('/admin/design/confirm', ['secret' => 'wrong', 'return' => $edit])->assertSessionHasErrors('secret');
        $this->post('/admin/design/confirm', ['secret' => 'password123', 'return' => $edit])->assertRedirect($edit);

        $this->get($edit)->assertOk()->assertSee('data-language="blade"', false);
        $this->save('theme/views/site/footer.blade.php', '<footer>new</footer>')->assertOk();
        $this->assertSame('code.confirmed', ActivityLog::where('action', 'code.confirmed')->value('action'));

        $this->travel(FileController::CONFIRM_MINUTES + 1)->minutes();
        $this->get($edit)->assertRedirect();
    }

    public function test_the_confirmation_uses_the_authenticator_when_it_is_on(): void
    {
        $secret = Totp::generateSecret();
        $this->admin->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => now()])->save();

        $this->actingAs($this->admin)->get('/admin/design/confirm')->assertOk()->assertSee('one-time-code');
        $this->post('/admin/design/confirm', ['secret' => 'password123'])->assertSessionHasErrors('secret');
        $this->post('/admin/design/confirm', ['secret' => Totp::code($secret, intdiv(time(), 30))])->assertRedirect(route('admin.design.files.index'));
    }

    public function test_the_confirmation_only_returns_inside_the_admin(): void
    {
        $this->actingAs($this->admin)->post('/admin/design/confirm', ['secret' => 'password123', 'return' => 'https://evil.example/admin'])
            ->assertRedirect(route('admin.design.files.index'));
    }

    public function test_the_code_editor_switch_turns_code_off_for_everyone(): void
    {
        config(['cms.code_editor' => false]);

        $this->actingAs($this->admin)->confirmed()->get('/admin/design/files/edit?path=theme/views/site/footer.blade.php')->assertForbidden();
        $this->get('/admin/design/confirm')->assertForbidden();
        $this->get('/admin/design/files/edit?path=theme/assets/site.css')->assertOk();
    }

    public function test_files_and_folders_are_created_renamed_and_deleted(): void
    {
        $this->actingAs($this->admin)->get(route('admin.design.files.create', ['dir' => 'theme/assets', 'kind' => 'file']))->assertOk()->assertSee('name="name"', false);

        $this->post(route('admin.design.files.store', ['dir' => 'theme/assets', 'kind' => 'file']), ['name' => 'extra.css'])
            ->assertRedirect(route('admin.design.files.edit', ['path' => 'theme/assets/extra.css']));
        $this->post(route('admin.design.files.store', ['dir' => 'theme/assets', 'kind' => 'file']), ['name' => 'extra.css'])
            ->assertSessionHasErrors('name');
        $this->post(route('admin.design.files.store', ['dir' => 'theme/assets', 'kind' => 'file']), ['name' => 'tool.exe'])
            ->assertSessionHasErrors('name');
        $this->post(route('admin.design.files.store', ['dir' => 'theme/../..', 'kind' => 'file']), ['name' => 'x.css'])->assertNotFound();
        $this->post(route('admin.design.files.store', ['dir' => 'theme', 'kind' => 'folder']), ['name' => 'fonts'])
            ->assertRedirect(route('admin.design.files.index', ['dir' => 'theme/fonts']));
        $this->assertDirectoryExists($this->base.'/theme/fonts');

        $this->post(route('admin.design.files.rename.save', ['path' => 'theme/assets/extra.css']), ['name' => 'more.css'])->assertRedirect();
        $this->assertFileExists($this->base.'/theme/assets/more.css');

        File::put($this->base.'/theme/assets/more.css', 'keep me');
        $this->from('/admin/design/files?dir=theme/assets')->post('/admin/design/files/delete', ['path' => 'theme/assets/more.css'])->assertRedirect();
        $this->assertFileDoesNotExist($this->base.'/theme/assets/more.css');
        $this->assertSame('keep me', FileRevision::where('path', 'theme/assets/more.css')->value('content'));

        $this->from('/admin/design/files?dir=theme')->post('/admin/design/files/delete', ['path' => 'theme/assets'])->assertSessionHas('error');
        $this->assertSame(['file.created', 'folder.created', 'file.renamed', 'file.deleted'], ActivityLog::orderBy('id')->pluck('action')->all());
    }

    public function test_uploads_accept_assets_and_svg_only_after_confirmation(): void
    {
        $this->actingAs($this->admin)->get(route('admin.design.files.upload', ['dir' => 'theme/assets']))->assertOk()->assertSee('enctype="multipart/form-data"', false);

        $this->post(route('admin.design.files.upload.save', ['dir' => 'theme/assets']), ['file' => UploadedFile::fake()->create('Brand Font.woff2', 4, 'font/woff2')])->assertRedirect();
        $this->assertFileExists($this->base.'/theme/assets/brand-font.woff2');

        $this->post(route('admin.design.files.upload.save', ['dir' => 'theme/assets']), ['file' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')])
            ->assertRedirect();
        $this->assertFileDoesNotExist($this->base.'/theme/assets/logo.svg');

        $this->post(route('admin.design.files.upload.save', ['dir' => 'theme/assets']), ['file' => UploadedFile::fake()->create('shell.php', 1, 'text/plain')])
            ->assertSessionHasErrors(['file' => 'File type .php is not allowed.']);
        $this->assertFileDoesNotExist($this->base.'/theme/assets/shell.php');
    }

    public function test_revisions_can_be_viewed_and_restored(): void
    {
        $this->actingAs($this->admin);
        $this->save('theme/assets/site.css', 'version two')->assertOk();
        $revision = FileRevision::where('path', 'theme/assets/site.css')->firstOrFail();

        $this->get(route('admin.design.files.revisions', ['path' => 'theme/assets/site.css']))->assertOk()->assertSee(route('admin.design.files.revision', $revision->id), false);
        $this->get(route('admin.design.files.revision', $revision->id))->assertOk()->assertSee('body { color: red; }');

        $this->post(route('admin.design.files.restore', $revision->id))->assertRedirect(route('admin.design.files.edit', ['path' => 'theme/assets/site.css']));
        $this->assertSame('body { color: red; }', File::get($this->base.'/theme/assets/site.css'));
        $this->assertSame('version two', SafeFiles::revisions('theme/assets/site.css')->first()->content);
    }

    public function test_full_backups_keep_theme_and_landing_files(): void
    {
        File::put($this->base.'/theme/.env', 'SECRET=1');
        File::put($this->base.'/theme/tool.exe', 'binary');

        $name = BackupService::create(true);
        $zip = new ZipArchive;
        $zip->open(BackupService::path($name));
        $this->assertNotFalse($zip->locateName('design/theme/assets/site.css'));
        $this->assertNotFalse($zip->locateName('design/landing/promo/landing.json'));
        $this->assertFalse($zip->locateName('design/theme/.env'));
        $this->assertFalse($zip->locateName('design/theme/tool.exe'));
        $zip->close();

        File::put($this->base.'/theme/assets/site.css', 'changed after the backup');
        File::delete($this->base.'/landing/promo/landing.json');
        BackupService::restore(BackupService::path($name));

        $this->assertSame('body { color: red; }', File::get($this->base.'/theme/assets/site.css'));
        $this->assertSame('{"title": "Promo"}', File::get($this->base.'/landing/promo/landing.json'));
    }

    public function test_a_database_backup_leaves_design_files_out(): void
    {
        $zip = new ZipArchive;
        $zip->open(BackupService::path(BackupService::create(false)));
        $this->assertFalse($zip->locateName('design/theme/assets/site.css'));
        $zip->close();
    }
}
