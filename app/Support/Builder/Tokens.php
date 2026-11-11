<?php

namespace App\Support\Builder;

/**
 * Style in page.json is a fixed set of tokens, never free CSS. Each token becomes a class in
 * public/css/builder.css: "b-pad-lg" for every screen, "bt-pad-md" for tablets, "bm-pad-sm" for phones.
 * A value is either one token ("lg") or {"base": "lg", "tablet": "md", "mobile": "sm"}.
 */
class Tokens
{
    public const BREAKPOINTS = ['base' => 'b', 'tablet' => 'bt', 'mobile' => 'bm'];

    public const SPACE = ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl'];

    public const ALIGN = ['start', 'center', 'end'];

    /** @var array<string, array{prefix:string, values:list<string|int>, responsive:bool}> */
    public const SECTION = [
        'padding' => ['prefix' => 'pad', 'values' => self::SPACE, 'responsive' => true],
        'gap' => ['prefix' => 'gap', 'values' => ['none', 'sm', 'md', 'lg', 'xl'], 'responsive' => true],
        'background' => ['prefix' => 'bg', 'values' => ['default', 'muted', 'primary', 'dark', 'image'], 'responsive' => false],
        'overlay' => ['prefix' => 'overlay', 'values' => ['none', 'light', 'dark'], 'responsive' => false],
        'width' => ['prefix' => 'w', 'values' => ['narrow', 'normal', 'wide', 'full'], 'responsive' => false],
        'valign' => ['prefix' => 'valign', 'values' => [...self::ALIGN, 'stretch'], 'responsive' => true],
        'text' => ['prefix' => 'text', 'values' => self::ALIGN, 'responsive' => true],
    ];

    public const COLUMN = [
        'align' => ['prefix' => 'align', 'values' => self::ALIGN, 'responsive' => true],
        'gap' => ['prefix' => 'cgap', 'values' => ['none', 'sm', 'md', 'lg'], 'responsive' => false],
    ];

    public const WIDGET = [
        'margin' => ['prefix' => 'mt', 'values' => self::SPACE, 'responsive' => true],
    ];

    /** Column widths, out of 12. */
    public const WIDTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    /**
     * Classes for one responsive value. Unknown tokens are dropped, so a bad value never reaches the page.
     *
     * @param  list<string|int>  $allowed
     */
    public static function classes(string $prefix, mixed $value, array $allowed): string
    {
        $classes = [];
        foreach (self::byBreakpoint($value) as $breakpoint => $token) {
            if (in_array($token, $allowed, true) || in_array((string) $token, array_map('strval', $allowed), true)) {
                $classes[] = self::BREAKPOINTS[$breakpoint].'-'.$prefix.'-'.$token;
            }
        }

        return implode(' ', $classes);
    }

    /** @return array<string, string|int> breakpoint => token, for the breakpoints that are set */
    public static function byBreakpoint(mixed $value): array
    {
        if (is_array($value)) {
            return array_filter(array_intersect_key($value, self::BREAKPOINTS), fn ($v) => is_string($v) || is_int($v));
        }

        return is_string($value) || is_int($value) ? ['base' => $value] : [];
    }

    /** Classes for a style object such as a section's "style", from a token table above. */
    public static function forStyle(array $style, array $table): string
    {
        $classes = [];
        foreach ($table as $key => $token) {
            if (! array_key_exists($key, $style)) {
                continue;
            }
            $value = $token['responsive'] ? $style[$key] : (is_array($style[$key]) ? null : $style[$key]);
            $classes[] = self::classes($token['prefix'], $value, $token['values']);
        }

        return trim(implode(' ', array_filter($classes)));
    }

    /** "hide": {"mobile": true} → "bm-hide". */
    public static function hide(mixed $hide): string
    {
        if (! is_array($hide)) {
            return '';
        }

        return implode(' ', array_map(fn ($breakpoint) => self::BREAKPOINTS[$breakpoint].'-hide', array_keys(array_filter(array_intersect_key($hide, self::BREAKPOINTS), fn ($v) => $v === true))));
    }

    /** Custom class names from the page's own style.css: letters, digits, "-" and "_" only. */
    public static function customClasses(mixed $class): string
    {
        if (! is_string($class)) {
            return '';
        }

        return implode(' ', array_filter(preg_split('/\s+/', trim($class)) ?: [], fn ($name) => preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/', $name) === 1));
    }
}
