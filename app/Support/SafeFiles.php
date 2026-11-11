<?php

namespace App\Support;

use App\Models\FileRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * All file access for the design tools goes through this class.
 * Paths are written as "<root>/<relative>", for example "theme/views/site/layout.blade.php".
 * Only the folders in config('cms.design_roots') are reachable.
 */
class SafeFiles
{
    public const MAX_EDIT = 524288; // 512 KB

    public const EDITABLE = ['blade.php', 'php', 'css', 'js', 'json', 'html', 'htm', 'txt', 'md', 'xml', 'svg'];

    public const CODE = ['blade.php', 'php', 'svg'];

    public const UPLOADABLE = ['css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'ico', 'woff', 'woff2', 'ttf', 'mp4', 'webm'];

    private const BLOCKED = ['.env', '.htaccess', '.htpasswd', '.git', '.gitignore', '.ds_store', 'composer.json', 'composer.lock', 'vendor', 'storage', 'node_modules'];

    /** @return array<string,string> */
    public static function roots(): array
    {
        return (array) config('cms.design_roots', []);
    }

    /**
     * Validate a path and return its parts.
     *
     * @return array{root:string,relative:string,absolute:string,is_dir:bool,is_file:bool}
     */
    public static function resolve(string $path, bool $mustExist = true): array
    {
        $path = trim(str_replace('\\', '/', $path));
        abort_if($path === '' || str_contains($path, "\0") || str_starts_with($path, '/'), 404);

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            abort_if($segment === '' || $segment === '.' || $segment === '..', 404);
            abort_if(self::blocked($segment), 404);
        }

        $rootKey = array_shift($segments);
        $roots = self::roots();
        abort_unless(isset($roots[$rootKey]), 404);

        $root = rtrim((string) $roots[$rootKey], '/');
        $relative = implode('/', $segments);
        $absolute = $relative === '' ? $root : $root.'/'.$relative;

        if (! is_dir($root)) {
            abort_if($mustExist, 404);
            File::ensureDirectoryExists($root);
        }

        $realRoot = realpath($root);
        abort_if($realRoot === false, 404);

        if (file_exists($absolute) || is_link($absolute)) {
            abort_if(is_link($absolute), 404);
            $real = realpath($absolute);
            abort_unless($real !== false && self::inside($real, $realRoot), 404);

            return [
                'root' => $rootKey,
                'relative' => $relative,
                'absolute' => $real,
                'is_dir' => is_dir($real),
                'is_file' => is_file($real),
            ];
        }

        abort_if($mustExist, 404);
        $parent = realpath(dirname($absolute));
        abort_unless($parent !== false && self::inside($parent, $realRoot), 404);

        return [
            'root' => $rootKey,
            'relative' => $relative,
            'absolute' => $absolute,
            'is_dir' => false,
            'is_file' => false,
        ];
    }

    public static function extension(string $path): string
    {
        $name = strtolower(basename(str_replace('\\', '/', $path)));

        return str_ends_with($name, '.blade.php') ? 'blade.php' : strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    public static function editable(string $path): bool
    {
        return in_array(self::extension($path), self::EDITABLE, true);
    }

    public static function requiresCode(string $path): bool
    {
        return in_array(self::extension($path), self::CODE, true);
    }

    public static function language(string $path): string
    {
        return match (self::extension($path)) {
            'blade.php', 'html', 'htm' => 'blade',
            'css' => 'css',
            'js' => 'js',
            'json' => 'json',
            'php' => 'php',
            'svg', 'xml' => 'xml',
            'md' => 'markdown',
            default => 'text',
        };
    }

    public static function read(string $path): string
    {
        $file = self::resolve($path);
        abort_unless($file['is_file'] && self::editable($path), 404);

        $size = filesize($file['absolute']);
        if ($size > self::MAX_EDIT) {
            throw new RuntimeException(__('This file is too large to edit (:size).', ['size' => Number::fileSize($size)]));
        }

        $content = (string) file_get_contents($file['absolute']);
        if (str_contains($content, "\0")) {
            throw new RuntimeException(__('This file is not plain text.'));
        }

        return $content;
    }

    public static function hash(string $path): ?string
    {
        $file = self::resolve($path);

        return $file['is_file'] ? hash_file('sha256', $file['absolute']) : null;
    }

    /** Returns the new hash. Throws FileConflictException when the file changed on disk. */
    /**
     * Writes a text file, creating it (and its folder) when it does not exist yet. A new file
     * with an expected hash is a conflict: someone else deleted it.
     */
    public static function put(string $path, string $content, ?string $expectedHash = null, bool $revision = true): string
    {
        self::ensureFolder(dirname(str_replace('\\', '/', trim($path))));
        $target = self::resolve($path, false);
        if (file_exists($target['absolute'])) {
            return self::write($path, $content, $expectedHash, $revision);
        }
        if ($expectedHash !== null) {
            throw new FileConflictException(__('This file changed on disk. Reload it before saving again.'));
        }
        abort_unless(self::editable($path) && ! self::requiresCode($path), 403);
        if (strlen($content) > self::MAX_EDIT) {
            throw new RuntimeException(__('This file is too large to edit (:size).', ['size' => Number::fileSize(strlen($content))]));
        }
        if (self::extension($path) === 'json' && json_decode($content) === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(__('This is not valid JSON: :error', ['error' => json_last_error_msg()]));
        }
        self::atomicWrite($target['absolute'], $content);

        return hash('sha256', $content);
    }

    /** Creates missing folders one level at a time, each checked like any other path. */
    private static function ensureFolder(string $path): void
    {
        $segments = explode('/', $path);
        for ($i = 2; $i <= count($segments); $i++) {
            $folder = self::resolve(implode('/', array_slice($segments, 0, $i)), false);
            if (! file_exists($folder['absolute'])) {
                mkdir($folder['absolute'], 0755) || throw new RuntimeException(__('Cannot write this file. Check the folder permissions.'));
            }
            abort_unless(is_dir($folder['absolute']), 404);
        }
    }

    public static function write(string $path, string $content, ?string $expectedHash = null, bool $revision = true): string
    {
        $file = self::resolve($path);
        abort_unless($file['is_file'] && self::editable($path), 403);

        if (strlen($content) > self::MAX_EDIT) {
            throw new RuntimeException(__('This file is too large to edit (:size).', ['size' => Number::fileSize(strlen($content))]));
        }
        if ($expectedHash !== null && hash_file('sha256', $file['absolute']) !== $expectedHash) {
            throw new FileConflictException(__('This file changed on disk. Reload it before saving again.'));
        }

        $extension = self::extension($path);
        if ($extension === 'json' && json_decode($content) === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(__('This is not valid JSON: :error', ['error' => json_last_error_msg()]));
        }
        self::lint($extension, $content);

        if ($revision) {
            self::record($path, (string) file_get_contents($file['absolute']));
        }
        self::atomicWrite($file['absolute'], $content);

        if (in_array($extension, ['php', 'blade.php'], true)) {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file['absolute'], true);
            }
            if ($extension === 'blade.php') {
                rescue(fn () => Artisan::call('view:clear'), report: false);
            }
        }

        return hash('sha256', $content);
    }

    public static function create(string $directory, string $name, string $kind = 'file'): string
    {
        $name = self::safeName($name);
        $dir = self::resolve($directory);
        abort_unless($dir['is_dir'], 404);

        $path = self::join($dir, $name);
        $target = self::resolve($path, false);
        if (file_exists($target['absolute'])) {
            throw new RuntimeException(__('A file or folder with this name already exists.'));
        }
        if ($kind === 'folder') {
            File::ensureDirectoryExists($target['absolute']);

            return $target['root'].'/'.$target['relative'];
        }

        if (! self::editable($path)) {
            throw new RuntimeException(__('This file type cannot be created here. Allowed: :types', ['types' => implode(', ', self::EDITABLE)]));
        }
        File::ensureDirectoryExists(dirname($target['absolute']));
        if (file_put_contents($target['absolute'], '') === false) {
            throw new RuntimeException(__('Cannot write this file. Check the folder permissions.'));
        }

        return $target['root'].'/'.$target['relative'];
    }

    public static function rename(string $path, string $name): string
    {
        $file = self::resolve($path);
        abort_if($file['relative'] === '', 403);

        $name = self::safeName($name);
        $directory = dirname($file['relative']);
        $newPath = ($directory === '.' ? '' : $directory.'/').$name;
        $target = self::resolve($file['root'].'/'.$newPath, false);
        if (file_exists($target['absolute'])) {
            throw new RuntimeException(__('A file or folder with this name already exists.'));
        }
        // A file keeps a type the design tools allow, so an image cannot become shell.phtml.
        $newExtension = self::extension($name);
        if (! is_dir($file['absolute']) && ! in_array($newExtension, [...self::EDITABLE, ...self::UPLOADABLE], true)) {
            throw new RuntimeException(__('File type :type is not allowed.', ['type' => $newExtension === '' ? '?' : '.'.$newExtension]));
        }
        if (! @rename($file['absolute'], $target['absolute'])) {
            throw new RuntimeException(__('Cannot rename this file. Check the folder permissions.'));
        }

        return $target['root'].'/'.$target['relative'];
    }

    /** Deletes a folder and everything in it. Text files are kept as revisions first; links are removed, never followed. */
    public static function deleteTree(string $path): void
    {
        $dir = self::resolve($path);
        abort_unless($dir['is_dir'] && $dir['relative'] !== '' && ! str_contains($dir['relative'], '/'), 403);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir['absolute'], \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $item) {
            $relative = $path.'/'.str_replace('\\', '/', substr($item->getPathname(), strlen($dir['absolute']) + 1));
            if ($item->isLink()) {
                @unlink($item->getPathname());
            } elseif ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                if (self::editable($relative) && $item->getSize() <= self::MAX_EDIT) {
                    self::record($relative, (string) file_get_contents($item->getPathname()));
                }
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir['absolute']) || throw new RuntimeException(__('Cannot delete this folder.'));
    }

    public static function delete(string $path, bool $revision = true): void
    {
        $file = self::resolve($path);
        abort_if($file['relative'] === '', 403);

        if ($file['is_dir']) {
            $entries = array_diff(scandir($file['absolute']) ?: [], ['.', '..']);
            if ($entries !== []) {
                throw new RuntimeException(__('This folder is not empty.'));
            }
            @rmdir($file['absolute']) || throw new RuntimeException(__('Cannot delete this folder.'));
        } else {
            // A deleted text file can be restored from its revisions.
            if ($revision && self::editable($path)) {
                self::record($path, (string) file_get_contents($file['absolute']));
            }
            @unlink($file['absolute']) || throw new RuntimeException(__('Cannot delete this file.'));
        }
    }

    public static function upload(string $directory, UploadedFile $file, bool $allowCode = false): string
    {
        $dir = self::resolve($directory);
        abort_unless($dir['is_dir'], 404);

        $name = strtolower(preg_replace('/[^A-Za-z0-9._-]/', '-', $file->getClientOriginalName()) ?: '');
        $name = ltrim($name, '.-');
        if ($name === '' || str_contains($name, '..') || self::blocked($name)) {
            throw new RuntimeException(__('This name is not allowed.'));
        }

        $extension = self::extension($name);
        if (! in_array($extension, self::UPLOADABLE, true) && ! ($allowCode && $extension === 'svg')) {
            throw new RuntimeException(__('File type :type is not allowed.', ['type' => $extension === '' ? '?' : '.'.$extension]));
        }

        $maxBytes = max(1, (int) setting('media_max_mb', 10)) * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException(__('File is larger than the :mb MB limit.', ['mb' => setting('media_max_mb', 10)]));
        }

        $mime = (string) $file->getMimeType();
        if (preg_match('#(php|html|xhtml|svg|phtml)#i', $mime)) {
            throw new RuntimeException(__('File type :type is not allowed.', ['type' => $mime]));
        }
        // The MIME type can come from the name; getimagesize() reads the file itself.
        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'ico'], true) && (! str_starts_with($mime, 'image/') || @getimagesize((string) $file->getRealPath()) === false)) {
            throw new RuntimeException(__('This file is not a valid image.'));
        }
        if (in_array($extension, ['mp4', 'webm'], true) && ! str_starts_with($mime, 'video/')) {
            throw new RuntimeException(__('This file is not a valid video.'));
        }

        $name = self::uniqueName($dir['absolute'], $name);
        $file->move($dir['absolute'], $name);

        return self::join($dir, $name);
    }

    /** @return list<array{name:string,path:string,type:string,size:int,modified:int,editable:bool,requires_code:bool}> */
    public static function entries(string $directory): array
    {
        $dir = self::resolve($directory);
        if (! is_dir($dir['absolute'])) {
            return [];
        }

        $entries = [];
        foreach (scandir($dir['absolute']) ?: [] as $name) {
            $path = $dir['absolute'].'/'.$name;
            if ($name === '.' || $name === '..' || self::blocked($name) || is_link($path)) {
                continue;
            }
            $relative = $dir['relative'] === '' ? $name : $dir['relative'].'/'.$name;
            $entries[] = [
                'name' => $name,
                'path' => $dir['root'].'/'.$relative,
                'type' => is_dir($path) ? 'dir' : 'file',
                'size' => is_file($path) ? (int) filesize($path) : 0,
                'modified' => (int) filemtime($path),
                'editable' => is_file($path) && self::editable($relative),
                'requires_code' => is_file($path) && self::requiresCode($relative),
            ];
        }

        usort($entries, fn ($a, $b) => [$a['type'] === 'dir' ? 0 : 1, strtolower($a['name'])] <=> [$b['type'] === 'dir' ? 0 : 1, strtolower($b['name'])]);

        return $entries;
    }

    /**
     * The sidebar tree for all editable roots.
     *
     * @return list<array{name:string,path:string,type:string,children:array}>
     */
    public static function tree(): array
    {
        $tree = [];
        foreach (array_keys(self::roots()) as $root) {
            $tree[] = [
                'name' => $root,
                'path' => $root,
                'type' => 'dir',
                'children' => self::children($root, (string) self::roots()[$root], $root, 0),
            ];
        }

        return $tree;
    }

    public static function revisions(string $path)
    {
        return FileRevision::where('path', $path)->latest('id')->limit(20)->get();
    }

    public static function restore(int $id): string
    {
        $revision = FileRevision::findOrFail($id);
        $path = (string) $revision->path;
        abort_unless(self::editable($path), 403);
        self::write($path, (string) $revision->content);

        return $path;
    }

    public static function publicUrl(string $path): ?string
    {
        $file = self::resolve($path);
        if (! $file['is_file']) {
            return null;
        }

        if ($file['root'] === 'theme') {
            $relative = $file['relative'];
            if (str_starts_with($relative, 'assets/')) {
                return url('_theme/'.substr($relative, 7)).'?v='.filemtime($file['absolute']);
            }

            return url('/');
        }

        if ($file['root'] === 'landing') {
            [$slug, $rest] = array_pad(explode('/', $file['relative'], 2), 2, '');
            $landing = Landing::find($slug);
            if (! $landing) {
                return null;
            }
            if ($rest === 'index.blade.php' || $rest === 'index.html') {
                return $landing['public'] || $slug === Landing::home() ? Locales::url($slug, app()->getLocale()) : null;
            }
            if (str_starts_with($rest, 'assets/')) {
                return url('_landing/'.$slug.'/'.$rest);
            }
        }

        return null;
    }

    private static function children(string $root, string $directory, string $display, int $depth): array
    {
        if ($depth > 8 || ! is_dir($directory)) {
            return [];
        }

        $children = [];
        foreach (scandir($directory) ?: [] as $name) {
            $absolute = $directory.'/'.$name;
            if ($name === '.' || $name === '..' || self::blocked($name) || is_link($absolute)) {
                continue;
            }
            $path = $display.'/'.$name;
            if (is_dir($absolute)) {
                $children[] = ['name' => $name, 'path' => $path, 'type' => 'dir', 'children' => self::children($root, $absolute, $path, $depth + 1)];
            } elseif (self::editable($path)) {
                $children[] = ['name' => $name, 'path' => $path, 'type' => 'file', 'children' => []];
            }
        }

        usort($children, fn ($a, $b) => [$a['type'] === 'dir' ? 0 : 1, strtolower($a['name'])] <=> [$b['type'] === 'dir' ? 0 : 1, strtolower($b['name'])]);

        return $children;
    }

    private static function record(string $path, string $content): void
    {
        if ($content === '' || strlen($content) > self::MAX_EDIT) {
            return;
        }

        FileRevision::create([
            'path' => $path,
            'content' => $content,
            'size' => strlen($content),
            'user_id' => auth()->id(),
        ]);

        $old = FileRevision::where('path', $path)->latest('id')->skip(20)->take(200)->pluck('id');
        if ($old->isNotEmpty()) {
            FileRevision::whereIn('id', $old)->delete();
        }
    }

    private static function atomicWrite(string $absolute, string $content): void
    {
        $directory = dirname($absolute);
        $temporary = tempnam($directory, '.cms-');
        if ($temporary === false) {
            throw new RuntimeException(__('Cannot write this file. Check the folder permissions.'));
        }
        if (file_put_contents($temporary, $content) === false) {
            @unlink($temporary);
            throw new RuntimeException(__('Cannot write this file. Check the folder permissions.'));
        }

        $mode = is_file($absolute) ? (fileperms($absolute) & 0777) : 0644;
        @chmod($temporary, $mode);
        if (! @rename($temporary, $absolute)) {
            @unlink($temporary);
            throw new RuntimeException(__('Cannot write this file. Check the folder permissions.'));
        }
    }

    /** Whether PHP and Blade files are syntax-checked on save. Without exec or a PHP CLI they are saved unchecked. */
    public static function canLint(): bool
    {
        if (! function_exists('exec')) {
            return false;
        }
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        return ! in_array('exec', $disabled, true) && self::phpCli() !== null;
    }

    private static string|false|null $phpCli = null;

    /**
     * The PHP command line binary for "php -l". Under php-fpm (Herd, Valet, most hosting) PHP_BINARY
     * is php-fpm itself, which has no -l and fails every check, so look for the CLI next to it.
     */
    public static function phpCli(): ?string
    {
        if (self::$phpCli !== null) {
            return self::$phpCli ?: null;
        }
        $candidates = [];
        if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'cli-server'], true) && PHP_BINARY !== '') {
            $candidates[] = PHP_BINARY;
        }
        if (PHP_BINARY !== '') {
            // Herd: .../bin/php84-fpm → .../bin/php84. Homebrew and Debian: .../sbin/php-fpm8.3 → .../bin/php8.3, then .../bin/php.
            $candidates[] = preg_replace('#-fpm$#', '', PHP_BINARY);
            $candidates[] = preg_replace('#/sbin/php-fpm([0-9.]*)$#', '/bin/php$1', PHP_BINARY);
            $candidates[] = dirname(PHP_BINARY, 2).'/bin/php';
        }
        $candidates[] = PHP_BINDIR.'/php';
        $candidates[] = (string) (new PhpExecutableFinder)->find(false);

        foreach (array_unique(array_filter($candidates)) as $binary) {
            if (! @is_file($binary) || ! @is_executable($binary) || str_contains(basename($binary), 'fpm') || str_contains(basename($binary), 'cgi')) {
                continue;
            }
            $output = [];
            $code = 1;
            @exec(escapeshellarg($binary).' -v 2>&1', $output, $code);
            if ($code === 0 && str_contains((string) ($output[0] ?? ''), '(cli)')) {
                return self::$phpCli = $binary;
            }
        }
        self::$phpCli = false;

        return null;
    }

    private static function lint(string $extension, string $content): void
    {
        if (! in_array($extension, ['php', 'blade.php'], true) || ! self::canLint()) {
            return;
        }
        // Raw Blade is mostly HTML and always passes php -l, so check the compiled template.
        if ($extension === 'blade.php') {
            try {
                $content = Blade::compileString($content);
            } catch (\Throwable $e) {
                throw new RuntimeException(__('This file has a syntax error: :error', ['error' => $e->getMessage()]));
            }
        }
        $temporary = tempnam(sys_get_temp_dir(), 'cms-lint-');
        if ($temporary === false) {
            return;
        }
        $php = $temporary.'.php';
        file_put_contents($php, $content);
        $output = [];
        $code = 1;
        @exec(escapeshellarg((string) self::phpCli()).' -l '.escapeshellarg($php).' 2>&1', $output, $code);
        @unlink($temporary);
        @unlink($php);

        if ($code !== 0) {
            // php -l names the temporary copy; the message is clearer without it.
            $error = trim(preg_replace('/\s+in \S+ on line/', ' on line', implode(' ', array_filter($output, fn ($line) => ! str_starts_with($line, 'Errors parsing')))));
            throw new RuntimeException(__('This file has a syntax error: :error', ['error' => $error]));
        }
    }

    /** "<root>/<relative>/<name>" for a resolved directory. */
    private static function join(array $dir, string $name): string
    {
        return $dir['root'].'/'.($dir['relative'] === '' ? '' : $dir['relative'].'/').$name;
    }

    private static function uniqueName(string $directory, string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : substr($name, 0, -(strlen($extension) + 1));
        $candidate = $name;
        for ($i = 1; file_exists($directory.'/'.$candidate); $i++) {
            $candidate = $base.'-'.$i.($extension === '' ? '' : '.'.$extension);
        }

        return $candidate;
    }

    private static function safeName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 190 || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..') || self::blocked($name)) {
            throw new RuntimeException(__('This name is not allowed.'));
        }

        return $name;
    }

    private static function blocked(string $name): bool
    {
        return in_array(strtolower($name), self::BLOCKED, true) || str_starts_with($name, '.');
    }

    private static function inside(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root.DIRECTORY_SEPARATOR);
    }
}
