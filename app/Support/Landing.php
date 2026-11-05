<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Landing pages are coded outside the CMS (by hand or with AI) in landing/<slug>/.
 * Each folder holds index.blade.php or index.html, landing.json, and optional assets.
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
                    'entry' => $entry,
                ] : null;
            })
            ->filter()
            ->values();
    }

    public static function options(): array
    {
        return self::all()->mapWithKeys(fn ($l) => [$l['slug'] => $l['title']])->all();
    }

    public static function assetUrl(string $path): string
    {
        return url('_landing/'.self::$current.'/'.ltrim($path, '/'));
    }

    public static function render(string $slug, ?Post $page = null): ?Response
    {
        $directory = self::directory($slug);
        if (! $directory) {
            return null;
        }

        self::$current = $slug;
        $seo = view('site.partials.seo', ['seo' => $page ? Seo::forPost($page) : Seo::defaults()])->render();
        $seo .= view('site.partials.head-hooks')->render();

        if (file_exists($directory.'/index.blade.php')) {
            $html = View::file($directory.'/index.blade.php', ['seo' => $seo, 'page' => $page])->render();
        } elseif (file_exists($directory.'/index.html')) {
            // Relative asset links work as written; SEO tags are added before </head>.
            $html = str_ireplace('</head>', $seo.'</head>', (string) file_get_contents($directory.'/index.html'));
            $base = url('_landing/'.$slug).'/';
            $html = preg_replace('/\b(href|src)=(["\'])(?:\.\/)?assets\//i', '$1=$2'.$base.'assets/', $html);
        } else {
            return null;
        }

        return response($html);
    }
}
