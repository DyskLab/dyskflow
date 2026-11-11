<?php

namespace App\Support\Builder;

/**
 * Free layout values for containers and widgets, the way Elementor's Container works: flex
 * direction and alignment, sizes in px/%/rem/em/vh/vw, spacing as CSS shorthand, and colors.
 * Every value is checked against a strict pattern, then written as CSS for one element
 * (".b-n-<id>"), with media queries for tablet (1023px) and mobile (767px). A value that does
 * not match is an error in the validator and is never printed.
 */
class Box
{
    public const LENGTH = '/^(0|-?\d{1,5}(\.\d{1,3})?(px|%|rem|em|vh|vw|dvh|svh)|auto)$/';

    /** Theme colors a color field may name, as var(--name). */
    public const THEME_COLORS = ['primary', 'primary-foreground', 'foreground', 'background', 'muted', 'muted-foreground', 'border', 'card', 'card-foreground', 'accent', 'accent-foreground', 'secondary', 'secondary-foreground', 'destructive', 'success', 'warning', 'brand-secondary', 'brand-secondary-foreground', 'brand-accent', 'brand-accent-foreground'];

    private const FLEX = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end', 'between' => 'space-between', 'around' => 'space-around', 'evenly' => 'space-evenly', 'stretch' => 'stretch'];

    /**
     * Keys of a container's "style". type: enum (with values), length, box (1 to 4 lengths), color, image.
     * negative and auto say whether a length may be below zero or "auto".
     *
     * @var array<string, array{type: string, values?: list<string>, responsive: bool, negative?: bool, auto?: bool}>
     */
    public const CONTAINER = [
        'direction' => ['type' => 'enum', 'values' => ['column', 'row'], 'responsive' => true],
        'justify' => ['type' => 'enum', 'values' => ['start', 'center', 'end', 'between', 'around', 'evenly'], 'responsive' => true],
        'align' => ['type' => 'enum', 'values' => ['stretch', 'start', 'center', 'end'], 'responsive' => true],
        'wrap' => ['type' => 'enum', 'values' => ['nowrap', 'wrap'], 'responsive' => true],
        'gap' => ['type' => 'length', 'responsive' => true],
        'width' => ['type' => 'length', 'responsive' => true, 'auto' => true],
        'min_height' => ['type' => 'length', 'responsive' => true],
        'content_width' => ['type' => 'enum', 'values' => ['boxed', 'full'], 'responsive' => false],
        'boxed_width' => ['type' => 'length', 'responsive' => true],
        'padding' => ['type' => 'box', 'responsive' => true],
        'margin' => ['type' => 'box', 'responsive' => true, 'negative' => true, 'auto' => true],
        'background' => ['type' => 'enum', 'values' => ['default', 'muted', 'primary', 'dark', 'image'], 'responsive' => false],
        'background_color' => ['type' => 'color', 'responsive' => false],
        'image' => ['type' => 'image', 'responsive' => false],
        'overlay' => ['type' => 'enum', 'values' => ['none', 'light', 'dark'], 'responsive' => false],
        'color' => ['type' => 'color', 'responsive' => false],
        'radius' => ['type' => 'length', 'responsive' => false],
        'border_width' => ['type' => 'length', 'responsive' => false],
        'border_color' => ['type' => 'color', 'responsive' => false],
        'shadow' => ['type' => 'enum', 'values' => ['none', 'sm', 'md', 'lg'], 'responsive' => false],
        'overflow' => ['type' => 'enum', 'values' => ['visible', 'hidden'], 'responsive' => false],
        'tag' => ['type' => 'enum', 'values' => ['div', 'section', 'header', 'footer', 'article', 'aside', 'nav'], 'responsive' => false],
    ];

    /** Keys of a widget's "style": its size and place inside a container. */
    public const WIDGET = [
        'width' => ['type' => 'length', 'responsive' => true, 'auto' => true],
        'align_self' => ['type' => 'enum', 'values' => ['stretch', 'start', 'center', 'end'], 'responsive' => true],
        'padding' => ['type' => 'box', 'responsive' => true],
        'margin' => ['type' => 'box', 'responsive' => true, 'negative' => true, 'auto' => true],
        'background_color' => ['type' => 'color', 'responsive' => false],
        'color' => ['type' => 'color', 'responsive' => false],
        'radius' => ['type' => 'length', 'responsive' => false],
    ];

    private const SHADOWS = ['sm' => '0 1px 3px rgb(0 0 0 / .12)', 'md' => '0 6px 20px rgb(0 0 0 / .12)', 'lg' => '0 18px 50px rgb(0 0 0 / .18)'];

    private const MEDIA = ['tablet' => '(max-width: 1023px)', 'mobile' => '(max-width: 767px)'];

    public static function length(mixed $value, bool $negative = false, bool $auto = false): bool
    {
        return is_string($value) && preg_match(self::LENGTH, $value) === 1
            && ($negative || ! str_starts_with($value, '-')) && ($auto || $value !== 'auto');
    }

    /** "10px", "10px 20px", up to four lengths, like CSS padding. */
    public static function box(mixed $value, bool $negative = false, bool $auto = false): bool
    {
        if (! is_string($value) || ($parts = preg_split('/\s+/', trim($value))) === false || count($parts) > 4 || trim($value) === '') {
            return false;
        }
        foreach ($parts as $part) {
            if (! self::length($part, $negative, $auto)) {
                return false;
            }
        }

        return true;
    }

    public static function color(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $number = '\s*\d{1,3}(\.\d+)?%?\s*';

        return preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) === 1
            || preg_match('/^(rgb|hsl)a?\('.$number.','.$number.','.$number.'(,\s*(0|1|0?\.\d+)\s*)?\)$/i', $value) === 1
            || in_array($value, ['transparent', 'currentColor'], true)
            || (preg_match('/^var\(--([a-z-]+)\)$/', $value, $m) === 1 && in_array($m[1], self::THEME_COLORS, true));
    }

    /** One value against its definition. Null when it is fine, or what is wrong. */
    public static function problem(array $definition, mixed $value): ?string
    {
        return match ($definition['type']) {
            'enum' => in_array($value, $definition['values'], true) ? null : 'must be one of: '.implode(', ', $definition['values']),
            'length' => self::length($value, $definition['negative'] ?? false, $definition['auto'] ?? false) ? null : 'must be a size such as 24px, 50%, 2rem or 100vh'.(! empty($definition['auto']) ? ', or auto' : ''),
            'box' => self::box($value, $definition['negative'] ?? false, $definition['auto'] ?? false) ? null : 'must be one to four sizes, such as "24px" or "16px 32px"',
            'color' => self::color($value) ? null : 'must be a color such as #ff5500, rgb(0, 0, 0), transparent or var(--primary)',
            'image' => Widgets::image($value) !== null ? null : 'must be uploads/… or https://…',
            default => 'is not known',
        };
    }

    /**
     * Problems in a style object, as "key: what is wrong" (a responsive key names the screen).
     *
     * @return list<string>
     */
    public static function check(mixed $style, array $table): array
    {
        if ($style === null) {
            return [];
        }
        if (! is_array($style) || array_is_list($style) && $style !== []) {
            return ['"style" must be an object'];
        }
        $problems = [];
        foreach ($style as $key => $value) {
            $definition = $table[$key] ?? null;
            if (! $definition) {
                $problems[] = "\"style.$key\" is not known. Use: ".implode(', ', array_keys($table));

                continue;
            }
            if ($definition['responsive'] && is_array($value)) {
                if ($unknown = array_diff(array_keys($value), array_keys(Tokens::BREAKPOINTS))) {
                    $problems[] = "\"style.$key\" has unknown screens: ".implode(', ', $unknown);
                }
                foreach (array_intersect_key($value, Tokens::BREAKPOINTS) as $screen => $one) {
                    if ($problem = self::problem($definition, $one)) {
                        $problems[] = "\"style.$key\" ($screen) $problem";
                    }
                }
            } elseif ($problem = self::problem($definition, $value)) {
                $problems[] = "\"style.$key\" $problem";
            }
        }

        return $problems;
    }

    /** True when a size is set on any screen, so a row container does not stretch it. */
    public static function sized(array $style): bool
    {
        return ! empty(Tokens::byBreakpoint($style['width'] ?? null));
    }

    /**
     * CSS for one container, by screen. $self is the element, $flex the one that lays out the
     * children: the inner box of a boxed container, or the element itself.
     *
     * @return array<string, list<string>> base|tablet|mobile => rules
     */
    public static function containerCss(string $self, string $flex, array $style): array
    {
        $rules = ['base' => [], 'tablet' => [], 'mobile' => []];
        $put = function (string $screen, string $selector, string $css) use (&$rules) {
            $rules[$screen][] = $selector.'{'.$css.'}';
        };

        foreach (self::screens($style, 'direction', self::CONTAINER) as $screen => $direction) {
            $put($screen, $flex, 'flex-direction:'.$direction);
            // In a row, containers without a size share the space; in a column they take their own height.
            $put($screen, $flex.'>.b-con:not(.b-sized)', $direction === 'row' ? 'flex:1 1 0' : 'flex:0 0 auto');
        }
        foreach (self::screens($style, 'justify', self::CONTAINER) as $screen => $value) {
            $put($screen, $flex, 'justify-content:'.self::FLEX[$value]);
        }
        foreach (self::screens($style, 'align', self::CONTAINER) as $screen => $value) {
            $put($screen, $flex, 'align-items:'.self::FLEX[$value]);
        }
        foreach (self::screens($style, 'wrap', self::CONTAINER) as $screen => $value) {
            $put($screen, $flex, 'flex-wrap:'.$value);
        }
        foreach (self::screens($style, 'gap', self::CONTAINER) as $screen => $value) {
            $put($screen, $flex, 'gap:'.$value);
        }
        foreach (self::screens($style, 'boxed_width', self::CONTAINER) as $screen => $value) {
            if ($flex !== $self) {
                $put($screen, $flex, 'max-width:'.$value);
            }
        }
        foreach (['width' => 'width', 'min_height' => 'min-height', 'padding' => 'padding', 'margin' => 'margin'] as $key => $property) {
            foreach (self::screens($style, $key, self::CONTAINER) as $screen => $value) {
                $put($screen, $self, $property.':'.$value);
            }
        }

        $base = [];
        if (isset($style['background_color']) && self::color($style['background_color'])) {
            $base[] = 'background-color:'.$style['background_color'];
        }
        if (isset($style['color']) && self::color($style['color'])) {
            $base[] = 'color:'.$style['color'];
        }
        if (isset($style['radius']) && self::length($style['radius'])) {
            $base[] = 'border-radius:'.$style['radius'];
        }
        if (isset($style['border_width']) && self::length($style['border_width'])) {
            $base[] = 'border:'.$style['border_width'].' solid '.(isset($style['border_color']) && self::color($style['border_color']) ? $style['border_color'] : 'var(--border)');
        }
        if (isset(self::SHADOWS[$style['shadow'] ?? ''])) {
            $base[] = 'box-shadow:'.self::SHADOWS[$style['shadow']];
        }
        if (($style['overflow'] ?? null) === 'hidden') {
            $base[] = 'overflow:hidden';
        }
        if ($base) {
            $put('base', $self, implode(';', $base));
        }

        return $rules;
    }

    /** @return array<string, list<string>> */
    public static function widgetCss(string $self, array $style): array
    {
        $rules = ['base' => [], 'tablet' => [], 'mobile' => []];
        foreach (self::screens($style, 'width', self::WIDGET) as $screen => $value) {
            $rules[$screen][] = $self.'{width:'.$value.';flex:0 1 auto}';
        }
        foreach (self::screens($style, 'align_self', self::WIDGET) as $screen => $value) {
            $rules[$screen][] = $self.'{align-self:'.self::FLEX[$value].'}';
        }
        foreach (['padding' => 'padding', 'margin' => 'margin'] as $key => $property) {
            foreach (self::screens($style, $key, self::WIDGET) as $screen => $value) {
                $rules[$screen][] = $self.'{'.$property.':'.$value.'}';
            }
        }
        $base = [];
        foreach (['background_color' => 'background-color', 'color' => 'color'] as $key => $property) {
            if (isset($style[$key]) && self::color($style[$key])) {
                $base[] = $property.':'.$style[$key];
            }
        }
        if (isset($style['radius']) && self::length($style['radius'])) {
            $base[] = 'border-radius:'.$style['radius'];
        }
        if ($base) {
            $rules['base'][] = $self.'{'.implode(';', $base).'}';
        }

        return $rules;
    }

    /** All rules of a page, base first, then tablet and mobile in their media queries. */
    public static function stylesheet(array $rules): string
    {
        $css = implode('', $rules['base'] ?? []);
        foreach (self::MEDIA as $screen => $media) {
            if (! empty($rules[$screen])) {
                $css .= '@media '.$media.'{'.implode('', $rules[$screen]).'}';
            }
        }

        return $css;
    }

    /** @return array<string, string> screen => valid value */
    private static function screens(array $style, string $key, array $table): array
    {
        $definition = $table[$key];
        $out = [];
        foreach (Tokens::byBreakpoint($style[$key] ?? null) as $screen => $value) {
            if (self::problem($definition, $value) === null) {
                $out[$screen] = (string) $value;
            }
        }

        return $out;
    }
}
