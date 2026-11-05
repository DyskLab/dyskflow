<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Landing pages are coded outside the CMS (by hand or with AI) in landing/<slug>/.
 * Each folder holds index.blade.php or index.html, landing.json, and optional assets.
 * One can be the homepage. Others get their own address when landing.json says "public": true.
 */
class Landing
{
    public const ASSET_TYPES = [
        'css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
    ];

    private static ?string $current = null;

    public static function directory(string $slug): ?string
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
            return null;
        }
        $path = base_path('landing/'.$slug);

        return is_dir($path) ? $path : null;
    }

    public static function all(): Collection
    {
        return collect(glob(base_path('landing/*'), GLOB_ONLYDIR) ?: [])
            ->map(function ($path) {
                $slug = basename($path);
                $meta = json_decode((string) @file_get_contents($path.'/landing.json'), true) ?: [];
                $entry = file_exists($path.'/index.blade.php') ? 'index.blade.php' : (file_exists($path.'/index.html') ? 'index.html' : null);

                return $entry && self::directory($slug) ? [
                    'slug' => $slug,
                    'title' => $meta['title'] ?? $slug,
                    'description' => $meta['description'] ?? '',
                    'public' => ($meta['public'] ?? false) === true,
                    'menu' => ($meta['menu'] ?? false) === true && ($meta['public'] ?? false) === true,
                    'entry' => $entry,
                ] : null;
            })
            ->filter()
            ->values();
    }

    /** The landing page used as the homepage, or null when the homepage shows something else or the folder is gone. */
    public static function home(): ?string
    {
        if (setting('home_type', 'landing') !== 'landing') {
            return null;
        }
        $slug = (string) setting('home_landing', 'dyskflow');

        return self::all()->contains('slug', $slug) ? $slug : null;
    }

    /**
     * Landing pages that asked for a place in the site menu. Skips one hidden by a page with the same slug.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function menu(): array
    {
        $pages = Post::where('type', 'page')->pluck('slug')->all();

        return self::all()->where('menu', true)
            ->reject(fn ($landing) => in_array($landing['slug'], $pages, true) || $landing['slug'] === self::home())
            ->map(fn ($landing) => ['label' => __($landing['title']), 'url' => Locales::url($landing['slug'], app()->getLocale())])
            ->values()->all();
    }

    public static function find(string $slug): ?array
    {
        return self::all()->firstWhere('slug', $slug);
    }

    public static function options(): array
    {
        return self::all()->mapWithKeys(fn ($l) => [$l['slug'] => $l['title']])->all();
    }

    public static function assetUrl(string $path): string
    {
        // These files are cached for a week, so the address has to change when the file does.
        $path = ltrim($path, '/');
        $file = base_path('landing/'.self::$current.'/'.$path);

        return url('_landing/'.self::$current.'/'.$path).(is_file($file) ? '?v='.filemtime($file) : '');
    }

    public static function render(string $slug, array $seo): ?Response
    {
        $directory = self::directory($slug);
        if (! $directory) {
            return null;
        }

        self::$current = $slug;
        $tags = view('site.partials.seo', ['seo' => $seo])->render();
        $tags .= view('site.partials.head-hooks')->render();

        if (file_exists($directory.'/index.blade.php')) {
            $html = View::file($directory.'/index.blade.php', ['seo' => $tags, 'page' => null, 'languageLink' => Seo::languageLink($seo)])->render();
        } elseif (file_exists($directory.'/index.html')) {
            // Relative asset links work as written; SEO tags are added before </head>.
            $html = str_ireplace('</head>', $tags.'</head>', (string) file_get_contents($directory.'/index.html'));
            $base = url('_landing/'.$slug).'/';
            $html = preg_replace('/\b(href|src)=(["\'])(?:\.\/)?assets\//i', '$1=$2'.$base.'assets/', $html);
        } else {
            return null;
        }

        return response($html);
    }
}
