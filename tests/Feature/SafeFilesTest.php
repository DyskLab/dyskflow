<?php

namespace Tests\Feature;

use App\Models\FileRevision;
use App\Support\FileConflictException;
use App\Support\SafeFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SafeFilesTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/cms-design-'.bin2hex(random_bytes(4));
        foreach (['theme', 'landing', 'plugins'] as $root) {
            File::ensureDirectoryExists($this->base.'/'.$root);
        }
        File::put($this->base.'/secret.txt', 'outside');
        config(['cms.design_roots' => [
            'theme' => $this->base.'/theme',
            'landing' => $this->base.'/landing',
            'plugins' => $this->base.'/plugins',
        ]]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function assertAborts(int $status, callable $callback): void
    {
        try {
            $callback();
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());

            return;
        }
        $this->fail("Expected HTTP $status.");
    }

    /** A refusal the user can fix: a message, not an HTTP error page. */
    private function assertRefused(callable $callback): void
    {
        try {
            $callback();
        } catch (HttpException $e) {
            $this->fail('Expected a message, got HTTP '.$e->getStatusCode().'.');
        } catch (RuntimeException $e) {
            $this->assertNotSame('', $e->getMessage());

            return;
        }
        $this->fail('Expected the action to be refused.');
    }

    public function test_paths_outside_the_design_roots_are_rejected(): void
    {
        foreach ([
            '../secret.txt', 'theme/../secret.txt', 'theme/a/../../secret.txt', '/etc/passwd', 'theme//x.css',
            "theme/a\0.css", 'unknown/x.css', 'theme/.env', 'theme/.htaccess', 'theme/vendor/x.php',
            'plugins/demo/storage/x.txt', 'plugins/demo/composer.json', '',
        ] as $path) {
            $this->assertAborts(404, fn () => SafeFiles::resolve($path, false));
        }
        $this->assertSame('theme', SafeFiles::resolve('theme')['root']);
    }

    public function test_symlinks_are_neither_followed_nor_listed(): void
    {
        if (! @symlink($this->base.'/secret.txt', $this->base.'/theme/link.txt')) {
            $this->markTestSkipped('This filesystem cannot create symlinks.');
        }
        symlink($this->base, $this->base.'/theme/up');

        $this->assertAborts(404, fn () => SafeFiles::read('theme/link.txt'));
        $this->assertAborts(404, fn () => SafeFiles::read('theme/up/secret.txt'));
        $this->assertSame([], SafeFiles::entries('theme'));
    }

    public function test_files_and_folders_are_created_inside_the_chosen_folder(): void
    {
        $this->assertSame('theme/site.css', SafeFiles::create('theme', 'site.css'));
        $this->assertSame('theme/views', SafeFiles::create('theme', 'views', 'folder'));
        $this->assertSame('theme/views/home.blade.php', SafeFiles::create('theme/views', 'home.blade.php'));

        $this->assertFileExists($this->base.'/theme/site.css');
        $this->assertFileExists($this->base.'/theme/views/home.blade.php');
        $this->assertSame(['views', 'site.css'], array_column(SafeFiles::entries('theme'), 'name'));

        $this->expectException(RuntimeException::class);
        SafeFiles::create('theme', 'site.css');
    }

    public function test_names_and_types_outside_the_allow_list_are_rejected(): void
    {
        foreach ([['tool.exe', 'file'], ['../x.css', 'file'], ['.env', 'file'], ['vendor', 'folder'], ['a/b.css', 'file']] as [$name, $kind]) {
            $this->assertRefused(fn () => SafeFiles::create('theme', $name, $kind));
        }
        $this->assertSame([], glob($this->base.'/theme/*'));

        $this->assertTrue(SafeFiles::requiresCode('theme/views/a.blade.php'));
        $this->assertTrue(SafeFiles::requiresCode('plugins/demo/plugin.php'));
        $this->assertFalse(SafeFiles::requiresCode('theme/assets/site.css'));
        $this->assertSame('blade', SafeFiles::language('a.blade.php'));
    }

    public function test_saving_keeps_the_previous_content_as_a_revision(): void
    {
        File::put($this->base.'/theme/site.css', 'body { color: red; }');
        $hash = SafeFiles::hash('theme/site.css');

        $newHash = SafeFiles::write('theme/site.css', 'body { color: blue; }', $hash);

        $this->assertSame('body { color: blue; }', SafeFiles::read('theme/site.css'));
        $this->assertSame($newHash, SafeFiles::hash('theme/site.css'));
        $this->assertSame('body { color: red; }', SafeFiles::revisions('theme/site.css')->first()->content);
        $this->assertSame(['site.css'], array_values(array_diff(scandir($this->base.'/theme'), ['.', '..'])), 'No temporary file is left behind.');

        $restored = SafeFiles::restore(SafeFiles::revisions('theme/site.css')->first()->id);
        $this->assertSame('theme/site.css', $restored);
        $this->assertSame('body { color: red; }', SafeFiles::read('theme/site.css'));
    }

    public function test_a_file_changed_on_disk_is_not_overwritten(): void
    {
        File::put($this->base.'/theme/site.css', 'a');
        $hash = SafeFiles::hash('theme/site.css');
        File::put($this->base.'/theme/site.css', 'changed elsewhere');

        try {
            SafeFiles::write('theme/site.css', 'mine', $hash);
            $this->fail('Expected a conflict.');
        } catch (FileConflictException) {
            $this->assertSame('changed elsewhere', File::get($this->base.'/theme/site.css'));
        }
    }

    public function test_only_the_last_twenty_revisions_are_kept(): void
    {
        File::put($this->base.'/theme/site.css', 'v0');
        foreach (range(1, 25) as $i) {
            SafeFiles::write('theme/site.css', "v$i");
        }

        $this->assertSame(20, FileRevision::where('path', 'theme/site.css')->count());
        $this->assertSame('v24', SafeFiles::revisions('theme/site.css')->first()->content);
    }

    public function test_invalid_json_and_broken_templates_are_not_saved(): void
    {
        File::put($this->base.'/landing/page.json', '{}');
        try {
            SafeFiles::write('landing/page.json', '{"a": ');
            $this->fail('Expected invalid JSON to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('JSON', $e->getMessage());
        }
        $this->assertSame('{}', File::get($this->base.'/landing/page.json'));

        if (! SafeFiles::canLint()) {
            $this->markTestSkipped('exec is not available, so templates are saved unchecked.');
        }

        File::put($this->base.'/theme/a.blade.php', 'ok');
        try {
            SafeFiles::write('theme/a.blade.php', '<p>@if($x</p>');
            $this->fail('Expected a Blade syntax error.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('syntax error', $e->getMessage());
            $this->assertStringNotContainsString('cms-lint-', $e->getMessage());
        }
        $this->assertSame('ok', File::get($this->base.'/theme/a.blade.php'));

        SafeFiles::write('theme/a.blade.php', '@if($x) <p>{{ $x }}</p> @endif');
        $this->assertStringContainsString('@endif', File::get($this->base.'/theme/a.blade.php'));
    }

    public function test_uploads_accept_assets_only(): void
    {
        File::ensureDirectoryExists($this->base.'/theme/assets');

        $path = SafeFiles::upload('theme/assets', UploadedFile::fake()->create('Main Style.CSS', 1, 'text/css'));
        $this->assertSame('theme/assets/main-style.css', $path);
        $this->assertFileExists($this->base.'/theme/assets/main-style.css');

        $again = SafeFiles::upload('theme/assets', UploadedFile::fake()->create('main-style.css', 1, 'text/css'));
        $this->assertSame('theme/assets/main-style-1.css', $again);

        $this->assertRefused(fn () => SafeFiles::upload('theme/assets', UploadedFile::fake()->create('shell.php', 1, 'text/plain')));
        $this->assertRefused(fn () => SafeFiles::upload('theme/assets', UploadedFile::fake()->create('page.html', 1, 'text/html')));
        $this->assertRefused(fn () => SafeFiles::upload('theme/assets', UploadedFile::fake()->create('icon.svg', 1, 'image/svg+xml')));
        $this->assertRefused(fn () => SafeFiles::upload('theme/assets', UploadedFile::fake()->create('.htaccess', 1, 'text/plain')));
        $this->assertSame(['main-style-1.css', 'main-style.css'], array_values(array_diff(scandir($this->base.'/theme/assets'), ['.', '..'])));
    }

    public function test_rename_and_delete_stay_inside_the_root(): void
    {
        File::ensureDirectoryExists($this->base.'/theme/assets');
        File::put($this->base.'/theme/assets/old.css', 'x');

        $this->assertSame('theme/assets/new.css', SafeFiles::rename('theme/assets/old.css', 'new.css'));
        $this->assertRefused(fn () => SafeFiles::rename('theme/assets/new.css', '../new.css'));
        $this->assertRefused(fn () => SafeFiles::rename('theme/assets/new.css', 'new.php5'));
        $this->assertFileExists($this->base.'/theme/assets/new.css');
        File::put($this->base.'/theme/assets/logo.png', 'png');
        $this->assertRefused(fn () => SafeFiles::rename('theme/assets/logo.png', 'logo.phtml'));
        $this->assertRefused(fn () => SafeFiles::rename('theme/assets/logo.png', 'logo'));
        $this->assertSame('theme/assets/brand.webp', SafeFiles::rename('theme/assets/logo.png', 'brand.webp'));
        File::delete($this->base.'/theme/assets/brand.webp');
        $this->assertAborts(403, fn () => SafeFiles::delete('theme'));

        try {
            SafeFiles::delete('theme/assets');
            $this->fail('A folder with files must not be deleted.');
        } catch (RuntimeException) {
            $this->assertDirectoryExists($this->base.'/theme/assets');
        }

        SafeFiles::delete('theme/assets/new.css');
        $this->assertFileDoesNotExist($this->base.'/theme/assets/new.css');
        $this->assertSame('x', SafeFiles::revisions('theme/assets/new.css')->first()->content);
    }
}
