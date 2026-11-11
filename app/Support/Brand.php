<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Global colors and fonts (Settings > Colors & fonts), like Elementor's Site Settings. They become
 * CSS variables printed on every public page, builder page and landing page that loads the
 * custom CSS (Theme::customTag), so the theme, the builder widgets and landing pages share them.
 *
 * Fonts are downloaded once from Google Fonts into public/uploads/fonts when the settings are
 * saved. Visitors load them from this site only: no request to Google, nothing to disclose.
 */
class Brand
{
    /** Brand colors: setting => CSS variable. Empty settings keep the theme's own color. */
    public const COLORS = [
        'brand_primary' => 'primary',
        'brand_secondary' => 'brand-secondary',
        'brand_accent' => 'brand-accent',
    ];

    /** Fonts that need no download. */
    public const SYSTEM_FONTS = [
        'system' => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        'serif' => 'ui-serif, Georgia, Cambria, "Times New Roman", serif',
        'rounded' => 'ui-rounded, "SF Pro Rounded", "Hiragino Maru Gothic ProN", Quicksand, system-ui, sans-serif',
        'mono' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
    ];

    /** Google Fonts offered in the settings: family => fallback stack. */
    public const GOOGLE_FONTS = [
        'Inter' => 'sans-serif', 'Plus Jakarta Sans' => 'sans-serif', 'Poppins' => 'sans-serif', 'Montserrat' => 'sans-serif',
        'DM Sans' => 'sans-serif', 'Manrope' => 'sans-serif', 'Outfit' => 'sans-serif', 'Sora' => 'sans-serif',
        'Space Grotesk' => 'sans-serif', 'Work Sans' => 'sans-serif', 'Nunito' => 'sans-serif', 'Rubik' => 'sans-serif',
        'Roboto' => 'sans-serif', 'Open Sans' => 'sans-serif', 'Lato' => 'sans-serif', 'Raleway' => 'sans-serif',
        'Figtree' => 'sans-serif', 'Lexend' => 'sans-serif', 'Playfair Display' => 'serif', 'Merriweather' => 'serif',
        'Lora' => 'serif', 'Fraunces' => 'serif', 'DM Serif Display' => 'serif', 'Source Serif 4' => 'serif',
        'JetBrains Mono' => 'monospace',
    ];

    public const RADII = ['0' => '0', 'sm' => '.375rem', 'md' => '.625rem', 'lg' => '1rem', 'xl' => '1.5rem'];

    /** @return array<string, string> choices for the font selects */
    public static function fontOptions(): array
    {
        return ['' => __('Theme default (Inter or the system font)'),
            'system' => __('System sans-serif (no download)'), 'serif' => __('System serif (no download)'),
            'rounded' => __('System rounded (no download)'), 'mono' => __('System monospace (no download)'),
        ] + array_combine(array_keys(self::GOOGLE_FONTS), array_keys(self::GOOGLE_FONTS));
    }

    /** The <style> with the brand variables, or an empty string when nothing is set. */
    public static function styleTag(): string
    {
        return rescue(function () {
            $css = self::css();

            return $css === '' ? '' : '<style id="cms-brand">'.$css.'</style>';
        }, '', false);
    }

    public static function css(): string
    {
        $root = [];
        $dark = [];
        foreach (self::COLORS as $setting => $var) {
            $hex = self::hex((string) setting($setting));
            if ($hex === null) {
                continue;
            }
            $root[] = "--$var:$hex";
            $root[] = "--$var-foreground:".self::readableOn($hex);
            if ($var === 'primary') {
                // Text in the brand color: a little darker on light pages, lighter on dark ones.
                $root[] = "--primary-text:color-mix(in oklch,$hex 82%,black)";
                $root[] = "--ring:color-mix(in oklch,$hex 70%,transparent)";
                $dark[] = "--primary-text:color-mix(in oklch,$hex 62%,white)";
            }
        }
        if (isset(self::RADII[(string) setting('brand_radius')])) {
            $root[] = '--radius:'.self::RADII[(string) setting('brand_radius')];
        }

        $fonts = '';
        $rules = '';
        foreach (['body' => 'brand_font_body', 'heading' => 'brand_font_heading'] as $role => $setting) {
            $family = (string) setting($setting);
            if ($family === '') {
                continue;
            }
            $stack = self::stack($family);
            if ($stack === null) {
                continue;
            }
            $root[] = "--font-$role:$stack";
            $fonts .= self::fontFaces($family);
            $rules .= $role === 'body'
                ? 'body{font-family:var(--font-body)}'
                : 'h1,h2,h3,h4,h5,h6,.b-heading,.site-title{font-family:var(--font-heading)}';
        }
        $size = (int) setting('brand_font_size');
        if ($size >= 13 && $size <= 22) {
            $rules .= "body{font-size:{$size}px}";
        }

        if (! $root && $rules === '') {
            return '';
        }

        return $fonts.($root ? ':root{'.implode(';', $root).'}' : '').($dark ? '.dark{'.implode(';', $dark).'}' : '').$rules;
    }

    /**
     * Downloads the chosen Google Fonts that are not on this server yet. Returns problems, if any,
     * to show after saving; the site then falls back to the next font in the stack.
     *
     * @return list<string>
     */
    public static function installFonts(): array
    {
        $problems = [];
        foreach (array_unique([(string) setting('brand_font_body'), (string) setting('brand_font_heading')]) as $family) {
            if (! isset(self::GOOGLE_FONTS[$family]) || is_file(self::fontDir($family).'/fonts.css')) {
                continue;
            }
            try {
                self::download($family);
            } catch (Throwable $e) {
                $problems[] = __('The font :font could not be downloaded (:message). Visitors see a similar system font until it is saved again.', ['font' => $family, 'message' => $e->getMessage()]);
            }
        }

        return $problems;
    }

    public static function fontDir(string $family): string
    {
        return public_path('uploads/fonts/'.Str::slug($family));
    }

    private static function download(string $family): void
    {
        $query = str_replace(' ', '+', $family).':wght@400;500;600;700';
        // A current browser's user agent gets WOFF2 files.
        $css = Http::withUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36')
            ->timeout(20)->get('https://fonts.googleapis.com/css2?family='.$query.'&display=swap')->throw()->body();

        // Only the Latin subsets: enough for Indonesian and English, and a fraction of the size.
        preg_match_all('#/\*\s*(latin|latin-ext)\s*\*/\s*(@font-face\s*\{[^}]+\})#', $css, $faces, PREG_SET_ORDER);
        if (! $faces) {
            throw new \RuntimeException(__('unexpected answer from Google Fonts'));
        }
        $dir = self::fontDir($family);
        File::ensureDirectoryExists($dir);
        $out = '';
        $files = [];
        foreach ($faces as $face) {
            $block = $face[2];
            if (! preg_match('#url\((https://fonts\.gstatic\.com/[^)]+\.woff2)\)#', $block, $m)) {
                continue;
            }
            $name = $files[$m[1]] ??= substr(sha1($m[1]), 0, 12).'.woff2';
            if (! is_file($dir.'/'.$name)) {
                File::put($dir.'/'.$name, Http::timeout(30)->get($m[1])->throw()->body());
            }
            $out .= str_replace($m[1], '{base}/'.$name, $block)."\n";
        }
        if ($out === '') {
            throw new \RuntimeException(__('no font files found'));
        }
        File::put($dir.'/fonts.css', $out);
    }

    /** The @font-face rules of a downloaded font, pointing at this site. */
    private static function fontFaces(string $family): string
    {
        if (! isset(self::GOOGLE_FONTS[$family])) {
            return '';
        }
        $file = self::fontDir($family).'/fonts.css';
        if (! is_file($file)) {
            return '';
        }
        $base = rtrim(asset('uploads/fonts/'.Str::slug($family)), '/');

        return str_replace(["\n", '{base}'], ['', $base], (string) file_get_contents($file));
    }

    private static function stack(string $family): ?string
    {
        if (isset(self::SYSTEM_FONTS[$family])) {
            return self::SYSTEM_FONTS[$family];
        }
        if (isset(self::GOOGLE_FONTS[$family])) {
            return '"'.$family.'", '.match (self::GOOGLE_FONTS[$family]) {
                'serif' => self::SYSTEM_FONTS['serif'],
                'monospace' => self::SYSTEM_FONTS['mono'],
                default => self::SYSTEM_FONTS['system'],
            };
        }

        return null;
    }

    public static function hex(string $value): ?string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^#([0-9a-f]{3})$/', $value, $m)) {
            $value = '#'.$m[1][0].$m[1][0].$m[1][1].$m[1][1].$m[1][2].$m[1][2];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
    }

    /** White or near-black, whichever reads better on the color (WCAG relative luminance). */
    public static function readableOn(string $hex): string
    {
        $channel = function (int $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = array_map(fn ($i) => $channel((int) hexdec(substr($hex, $i, 2))), [1, 3, 5]);
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // Contrast with white is (1.05)/(L+.05); with #111 it is (L+.05)/(.0056+.05).
        return 1.05 / ($luminance + 0.05) >= ($luminance + 0.05) / 0.0556 ? '#ffffff' : '#111111';
    }
}
