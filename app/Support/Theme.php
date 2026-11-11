<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Edits to the default site theme live in theme/ instead of resources/views and public/.
 * That folder is protected from updates. Only site.* views can be overridden, so a broken
 * override can break the public site but never the admin used to fix it.
 * CMS_THEME_OVERRIDES=false turns every override off.
 */
class Theme
{
    public const ASSETS = ['css/ui.css', 'css/site.css', 'js/site.js'];

    /**
     * Custom CSS and JavaScript for the whole public site, in theme/assets/. They load after the
     * theme's own files on every public page (theme views, builder pages, and landing pages that
     * include the site header or footer), and stop loading with CMS_THEME_OVERRIDES=false.
     */
    public const CUSTOM = ['css' => 'css/custom.css', 'js' => 'js/custom.js'];

    public static function root(): string
    {
        return rtrim((string) config('cms.design_roots.theme'), '/');
    }

    public static function viewsPath(): string
    {
        return self::root().'/views';
    }

    public static function assetsPath(): string
    {
        return self::root().'/assets';
    }

    public static function enabled(): bool
    {
        return (bool) config('cms.theme_overrides', true) && self::root() !== '';
    }

    public static function apply(): void
    {
        // Laravel accepts only a closure or a class name here, not an array callable.
        View::creator('site.*', fn (ViewInstance $view) => self::swap($view));
    }

    /** Point a site.* view at its override in theme/views, when there is one. */
    public static function swap(ViewInstance $view): void
    {
        if (! self::enabled()) {
            return;
        }
        $file = self::viewsPath().'/'.str_replace('.', '/', $view->name()).'.blade.php';
        if (is_file($file)) {
            $view->setPath($file);
        }
    }

    /**
     * Every core theme file, with its override status.
     *
     * @return list<array{path:string,kind:string,label:string,overridden:bool}>
     */
    public static function files(): array
    {
        $files = [];
        foreach (self::coreViews() as $relative) {
            $path = 'site/'.$relative;
            $files[] = [
                'path' => $path,
                'kind' => 'view',
                'label' => $path,
                'overridden' => is_file(self::overridePath($path)),
            ];
        }
        foreach (self::ASSETS as $path) {
            $files[] = [
                'path' => $path,
                'kind' => 'asset',
                'label' => $path,
                'overridden' => is_file(self::overridePath($path)),
            ];
        }

        return $files;
    }

    /** @return list<string> */
    public static function paths(): array
    {
        return array_map(fn (array $file) => $file['path'], self::files());
    }

    public static function overridePath(string $path): string
    {
        return str_starts_with($path, 'site/')
            ? self::viewsPath().'/'.$path
            : self::assetsPath().'/'.$path;
    }

    public static function sourcePath(string $path): string
    {
        return str_starts_with($path, 'site/')
            ? resource_path('views/'.$path)
            : public_path($path);
    }

    /** Copy a core file into theme/ so it can be edited. Returns its path in the file manager. */
    public static function override(string $path): string
    {
        $path = self::normalize($path);
        abort_unless(in_array($path, self::paths(), true), 404);

        $source = self::sourcePath($path);
        abort_unless(is_file($source), 404);

        $target = self::overridePath($path);
        File::ensureDirectoryExists(dirname($target));
        if (! is_file($target)) {
            File::copy($source, $target);
        }

        return self::fileManagerPath($path);
    }

    /** Remove an override so the core file is used again. The removed content is kept as a revision. */
    public static function reset(string $path): void
    {
        $path = self::normalize($path);
        abort_unless(in_array($path, self::paths(), true), 404);

        if (is_file(self::overridePath($path))) {
            SafeFiles::delete(self::fileManagerPath($path));
        }
    }

    public static function fileManagerPath(string $path): string
    {
        return str_starts_with($path, 'site/') ? 'theme/views/'.$path : 'theme/assets/'.$path;
    }

    /** theme/assets/css/custom.css or js/custom.js, relative to the design roots, for the file editor. */
    public static function customPath(string $kind): string
    {
        return 'theme/assets/'.self::CUSTOM[$kind];
    }

    /**
     * The <link> or <script> of the custom CSS or JavaScript, once per request, or an empty string
     * when the file is missing or empty. Printed in the layouts and again in the header and footer
     * partials for landing pages; the second call prints nothing.
     */
    public static function customTag(string $kind): string
    {
        $key = 'theme_custom_'.$kind;
        if (! isset(self::CUSTOM[$kind]) || request()->attributes->get($key)) {
            return '';
        }
        request()->attributes->set($key, true);
        // The global colors and fonts come first, so the custom CSS can still change them.
        $brand = $kind === 'css' ? Brand::styleTag() : '';
        if (! self::enabled()) {
            return $brand;
        }
        $file = self::assetsPath().'/'.self::CUSTOM[$kind];
        // Long-running PHP workers would otherwise keep the size of a file edited since.
        clearstatcache(true, $file);
        if (! is_file($file) || filesize($file) === 0) {
            return $brand;
        }
        $url = e(url('_theme/'.self::CUSTOM[$kind]).'?v='.filemtime($file));

        return $kind === 'css' ? $brand.'<link rel="stylesheet" href="'.$url.'">' : '<script src="'.$url.'"></script>';
    }

    /** The versioned URL of a theme asset, or of the core asset when there is no override. */
    public static function assetUrl(string $path): string
    {
        $relative = ltrim($path, '/');
        $file = self::assetsPath().'/'.$relative;

        return self::enabled() && is_file($file)
            ? url('_theme/'.$relative).'?v='.filemtime($file)
            : asset_v($relative);
    }

    /** Serve one file from theme/assets with the same allow-list as landing assets. */
    public static function assetResponse(string $path): Response
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(self::enabled() && isset(Landing::ASSET_TYPES[$extension]), 404);

        $base = realpath(self::assetsPath());
        abort_unless($base !== false, 404);

        $file = realpath(self::assetsPath().'/'.$path);
        abort_unless($file && str_starts_with($file, $base.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, [
            'Content-Type' => Landing::ASSET_TYPES[$extension],
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return list<string> relative paths below resources/views/site */
    private static function coreViews(): array
    {
        $base = resource_path('views/site');
        if (! is_dir($base)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            }
        }
        sort($files);

        return $files;
    }

    private static function normalize(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return str_contains($path, '..') ? '' : $path;
    }
}
