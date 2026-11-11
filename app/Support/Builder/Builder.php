<?php

namespace App\Support\Builder;

use App\Models\Post;
use App\Support\Seo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * Builder pages are pages (posts of type "page") whose layout is JSON in posts.builder, the way
 * Elementor keeps its data. Unpublished changes are in posts.builder_draft. The visual builder
 * edits that JSON, AI tools use builder:export and builder:import, and the server renders it to
 * HTML with the widget views, so visitors and search engines get a finished page. See docs/BUILDER.md.
 */
class Builder
{
    public const VERSION = 1;

    /** A section, and one inner section inside one of its columns. */
    public const MAX_DEPTH = 2;

    /** Containers nest up to this many levels, counting the one in "sections". */
    public const MAX_CONTAINER_DEPTH = 6;

    /** CSS for the free values of containers and widgets on the page being rendered (Box). */
    private static array $rules = ['base' => [], 'tablet' => [], 'mobile' => []];

    public const ID_PATTERN = '/^[a-z][a-z0-9]{2,15}$/';

    /** Widgets whose fields are raw markup, with those fields. Adding or changing them needs code.manage. */
    public const CODE_WIDGETS = ['html' => ['html']];

    /** Page keys that are code. The page's JavaScript runs for every visitor, so it needs code.manage too. */
    public const CODE_KEYS = ['js'];

    /** Keys first in this order, so exported files and diffs from the builder and from an AI stay small. */
    private const KEY_ORDER = ['$schema', 'version', 'layout', 'css', 'js', 'settings', 'sections', 'id', 'type', 'style', 'width', 'align', 'gap', 'anchor', 'class', 'hide', 'margin', 'columns', 'widgets', 'children'];

    public static function newId(string $prefix = 'w'): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $id = $prefix[0] ?? 'w';
        for ($i = 0; $i < 4; $i++) {
            $id .= $alphabet[random_int(0, 35)];
        }

        return $id;
    }

    /** A new, empty page. */
    public static function blank(string $layout = 'site'): array
    {
        return ['version' => self::VERSION, 'layout' => $layout, 'sections' => []];
    }

    /** A classic page moved to the builder: its content becomes one text widget, so nothing is lost. */
    public static function fromContent(?string $html): array
    {
        $spec = self::blank();
        if (trim(strip_tags((string) $html, '<img><iframe><video>')) !== '') {
            $spec['sections'][] = ['id' => self::newId('s'), 'type' => 'section', 'columns' => [
                ['id' => self::newId('c'), 'width' => 12, 'widgets' => [['id' => self::newId('w'), 'type' => 'text', 'html' => (string) $html]]],
            ]];
        }

        return $spec;
    }

    /**
     * Stops a user without code.manage from adding or changing code widgets or the page's
     * JavaScript: in the editor, a copy,
     * a template or a restored revision. Values the page already has, published or in its draft,
     * may stay, move or be copied. Console commands have no user and are not limited.
     *
     * @param  array<string, mixed>|null  $spec  the layout about to be saved
     * @param  list<array<string, mixed>|null>  $also  other layouts whose values count as already there
     *
     * @throws RuntimeException
     */
    public static function guardCode(?Post $post, ?array $spec, array $also = []): void
    {
        $user = auth()->user();
        if (! $user || $user->can('code.manage') || ! $spec) {
            return;
        }
        $known = [];
        foreach ([...$also, $post?->builderSpec(), $post?->builderSpec(true)] as $page) {
            foreach ([...self::codeValues($page), ...self::pageCode($page)] as $value) {
                $known[$value] = true;
            }
        }
        foreach (self::codeValues($spec) as $value) {
            if (! isset($known[$value])) {
                throw new RuntimeException(__('Only users who may edit code can add or change HTML widgets. Use the other widgets, or ask someone with that permission.'));
            }
        }
        foreach (self::pageCode($spec) as $value) {
            if (! isset($known[$value])) {
                throw new RuntimeException(__('Only users who may edit code can add or change the JavaScript of a page.'));
            }
        }
    }

    /** @return list<string> */
    private static function pageCode(?array $spec): array
    {
        $values = [];
        foreach (self::CODE_KEYS as $key) {
            if (is_string($spec[$key] ?? null) && trim($spec[$key]) !== '') {
                $values[] = 'page:'.$key.':'.$spec[$key];
            }
        }

        return $values;
    }

    /** Every code-widget field value in a page, as comparable strings. @return list<string> */
    private static function codeValues(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }
        $values = [];
        if (is_string($node['type'] ?? null) && isset(self::CODE_WIDGETS[$node['type']])) {
            foreach (self::CODE_WIDGETS[$node['type']] as $field) {
                $value = $node[$field] ?? '';
                if (is_array($value)) {
                    ksort($value);
                }
                if ($value !== '' && $value !== [] && $value !== null) {
                    $values[] = $node['type'].':'.$field.':'.json_encode($value, JSON_UNESCAPED_UNICODE);
                }
            }
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                array_push($values, ...self::codeValues($child));
            }
        }

        return $values;
    }

    /**
     * Ready-made pages in resources/builder-templates, to start a new page from.
     *
     * @return array<string, array{title: string, description: string}>
     */
    public static function templates(): array
    {
        $templates = [];
        foreach (glob(resource_path('builder-templates/*.json')) ?: [] as $file) {
            $spec = json_decode((string) file_get_contents($file), true);
            if (is_array($spec) && is_array($spec['template'] ?? null)) {
                $templates[basename($file, '.json')] = ['title' => (string) ($spec['template']['title'] ?? basename($file, '.json')), 'description' => (string) ($spec['template']['description'] ?? '')];
            }
        }

        return $templates;
    }

    /** A template's page, without its "template" description. */
    public static function template(string $name): ?array
    {
        if (! preg_match('/^[a-z0-9-]+$/', $name) || ! is_file($file = resource_path('builder-templates/'.$name.'.json'))) {
            return null;
        }
        $spec = json_decode((string) file_get_contents($file), true);
        unset($spec['template']);

        return is_array($spec) ? $spec : null;
    }

    /** How the layout is kept in posts.builder and posts.builder_draft. */
    public static function compact(array $spec): string
    {
        return (string) json_encode(self::ordered($spec), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** For builder:export: a stable key order, two-space indent and readable Unicode, ending in a newline. */
    public static function encode(array $spec): string
    {
        $json = json_encode(self::ordered($spec), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return preg_replace_callback('/^( +)/m', fn ($m) => str_repeat(' ', intdiv(strlen($m[1]), 2)), (string) $json)."\n";
    }

    /**
     * The whole HTML document for a builder page. $seoTags is the <head> markup for its SEO.
     * In preview mode every node carries data-b-id and a broken widget shows its error instead of disappearing.
     */
    public static function render(array $spec, string $seoTags, array $seo, bool $preview = false): string
    {
        $layout = in_array($spec['layout'] ?? 'site', Validator::LAYOUTS, true) ? ($spec['layout'] ?? 'site') : 'site';

        $languageLink = Seo::languageLink($seo);
        $previousLink = Context::$languageLink;
        Context::$languageLink = $languageLink;
        try {
            [$body, $layoutCss] = self::body($spec['sections'] ?? [], $preview);
        } finally {
            Context::$languageLink = $previousLink;
        }

        return view('builder.page', [
            'seo' => new HtmlString($seoTags),
            'builder' => new HtmlString($body),
            'layoutCss' => $layoutCss,
            'page' => $spec,
            'preview' => $preview,
            'layout' => $layout,
            'languageLink' => $languageLink,
            'css' => self::css($spec['css'] ?? ''),
            // The editor preview shares the admin's origin, so the page's own script runs only on the site.
            'js' => $preview ? '' : self::script($spec['js'] ?? ''),
        ])->render();
    }

    /** The page's CSS for a <style> element: "<" is written as a CSS escape, so it cannot close the element. */
    public static function css(mixed $css): string
    {
        return is_string($css) ? str_replace('<', '\3C ', trim($css)) : '';
    }

    /** The page's JavaScript for a <script> element, which a "</script" inside a string cannot end early. */
    public static function script(mixed $js): string
    {
        return is_string($js) ? preg_replace(['#</(script)#i', '#<!--#'], ['<\\/$1', '<\\!--'], trim($js)) : '';
    }

    /**
     * The HTML of the page's sections and containers, with the CSS their free values need.
     *
     * @return array{0: string, 1: string}
     */
    public static function body(array $sections, bool $preview = false): array
    {
        self::$rules = ['base' => [], 'tablet' => [], 'mobile' => []];
        $html = self::sections($sections, $preview);
        // "<" cannot appear in values that passed Box, but the stylesheet goes in a <style> all the same.
        $css = str_replace('<', '\3C ', Box::stylesheet(self::$rules));
        self::$rules = ['base' => [], 'tablet' => [], 'mobile' => []];

        return [$html, $css];
    }

    /** @param  list<mixed>  $sections */
    public static function sections(array $sections, bool $preview = false, int $depth = 1): string
    {
        $html = '';
        foreach ($sections as $section) {
            if (is_array($section) && ($section['type'] ?? null) === 'container') {
                $html .= self::container($section, $preview, 1);
            } elseif (is_array($section) && is_array($section['columns'] ?? null)) {
                $html .= self::section($section, $preview, $depth);
            }
        }

        return $html;
    }

    /**
     * A flex container, like Elementor's: children are widgets and other containers. A boxed
     * container (the default at the top) keeps its content in a centered inner box.
     */
    private static function container(array $node, bool $preview, int $depth): string
    {
        $style = is_array($node['style'] ?? null) ? $node['style'] : [];
        $id = is_string($node['id'] ?? null) && preg_match(self::ID_PATTERN, $node['id']) ? $node['id'] : null;
        $boxed = ($style['content_width'] ?? ($depth === 1 ? 'boxed' : 'full')) === 'boxed';
        $tag = in_array($style['tag'] ?? null, Box::CONTAINER['tag']['values'], true) ? $style['tag'] : ($depth === 1 ? 'section' : 'div');

        if ($id) {
            $self = '.b-n-'.$id;
            self::addRules(Box::containerCss($self, $boxed ? $self.'>.b-con-inner' : $self, $style));
        }
        $image = ($style['background'] ?? null) === 'image' ? Widgets::image($style['image'] ?? null) : null;
        $tokens = [];
        foreach (['background' => 'bg', 'overlay' => 'overlay'] as $key => $prefix) {
            if (in_array($style[$key] ?? null, Box::CONTAINER[$key]['values'], true)) {
                $tokens[] = 'b-'.$prefix.'-'.$style[$key];
            }
        }
        $classes = trim(implode(' ', array_filter([
            'b-con',
            $depth === 1 ? 'b-con-top' : null,
            $boxed ? 'b-boxed' : null,
            $id ? 'b-n-'.$id : null,
            Box::sized($style) ? 'b-sized' : null,
            ...$tokens,
            Tokens::hide($node['hide'] ?? null),
            Tokens::customClasses($node['class'] ?? null),
        ])));

        $children = '';
        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            if (($child['type'] ?? null) === 'container') {
                $children .= $depth < self::MAX_CONTAINER_DEPTH ? self::container($child, $preview, $depth + 1) : '';
            } elseif (($child['type'] ?? null) !== 'inner-section') {
                $children .= self::widget($child, $preview, self::MAX_DEPTH);
            }
        }
        if ($preview && $children === '') {
            $children = '<div class="b-empty" data-b-empty>'.e(__('Empty container')).'</div>';
        }

        return '<'.$tag.' class="'.e($classes).'"'
            .self::attributes($node, $preview)
            .($image ? ' style="--b-image:url(&quot;'.e($image).'&quot;)"' : '')
            .'>'.($boxed ? '<div class="b-con-inner">'.$children.'</div>' : $children).'</'.$tag.'>';
    }

    private static function addRules(array $rules): void
    {
        foreach ($rules as $screen => $list) {
            array_push(self::$rules[$screen], ...$list);
        }
    }

    private static function section(array $section, bool $preview, int $depth): string
    {
        $style = is_array($section['style'] ?? null) ? $section['style'] : [];
        $classes = trim(implode(' ', array_filter([
            $depth > 1 ? 'b-section b-inner-section' : 'b-section',
            Tokens::forStyle($style, Tokens::SECTION),
            Tokens::hide($section['hide'] ?? null),
            Tokens::customClasses($section['class'] ?? null),
        ])));
        $image = ($style['background'] ?? null) === 'image' ? Widgets::image($style['image'] ?? null) : null;

        $columns = '';
        foreach ($section['columns'] as $column) {
            if (is_array($column)) {
                $columns .= self::column($column, $preview, $depth);
            }
        }

        $tag = $depth > 1 ? 'div' : 'section';

        return '<'.$tag.' class="'.e($classes).'"'
            .self::attributes($section, $preview)
            .($image ? ' style="--b-image:url(&quot;'.e($image).'&quot;)"' : '')
            .'><div class="b-container"><div class="b-row">'.$columns.'</div></div></'.$tag.'>';
    }

    private static function column(array $column, bool $preview, int $depth): string
    {
        $classes = trim(implode(' ', array_filter([
            'b-col',
            Tokens::classes('col', $column['width'] ?? 12, Tokens::WIDTHS),
            Tokens::forStyle($column, Tokens::COLUMN),
            Tokens::hide($column['hide'] ?? null),
            Tokens::customClasses($column['class'] ?? null),
        ])));

        $widgets = '';
        foreach (is_array($column['widgets'] ?? null) ? $column['widgets'] : [] as $widget) {
            if (is_array($widget)) {
                $widgets .= self::widget($widget, $preview, $depth);
            }
        }
        if ($preview && $widgets === '') {
            $widgets = '<div class="b-empty" data-b-empty>'.e(__('Empty column')).'</div>';
        }

        return '<div class="'.e($classes).'"'.self::attributes($column, $preview).'>'.$widgets.'</div>';
    }

    private static function widget(array $widget, bool $preview, int $depth): string
    {
        $type = (string) ($widget['type'] ?? '');
        if ($type === 'inner-section') {
            return $depth < self::MAX_DEPTH && is_array($widget['columns'] ?? null) ? self::section($widget, $preview, $depth + 1) : '';
        }

        $definition = Widgets::get($type);
        if (! $definition) {
            return $preview ? self::problem($widget, __('There is no widget ":type".', ['type' => $type])) : '';
        }

        try {
            $inner = trim(View::file($definition['view'], [
                'w' => Widgets::props($definition, $widget),
                'node' => ['id' => $widget['id'] ?? null, 'type' => $type],
                'preview' => $preview,
            ])->render());
        } catch (Throwable $e) {
            Log::error('Builder widget "'.$type.'" failed: '.$e->getMessage());

            return $preview ? self::problem($widget, $e->getMessage()) : '';
        }
        if ($inner === '' && ! $preview) {
            return '';
        }

        $box = is_array($widget['style'] ?? null) ? $widget['style'] : [];
        $id = is_string($widget['id'] ?? null) && preg_match(self::ID_PATTERN, $widget['id']) ? $widget['id'] : null;
        if ($box && $id) {
            self::addRules(Box::widgetCss('.b-n-'.$id, $box));
        }
        $classes = trim(implode(' ', array_filter([
            'b-w b-w-'.$type,
            $box && $id ? 'b-n-'.$id : null,
            Tokens::forStyle($widget, Tokens::WIDGET),
            Tokens::hide($widget['hide'] ?? null),
            Tokens::customClasses($widget['class'] ?? null),
        ])));

        return '<div class="'.e($classes).'"'.self::attributes($widget, $preview).'>'.$inner.'</div>';
    }

    private static function attributes(array $node, bool $preview): string
    {
        $out = '';
        if (is_string($node['anchor'] ?? null) && preg_match('/^[a-z][a-z0-9-]{0,60}$/', $node['anchor'])) {
            $out .= ' id="'.e($node['anchor']).'"';
        }
        if ($preview && is_string($node['id'] ?? null)) {
            $out .= ' data-b-id="'.e($node['id']).'"';
        }

        return $out;
    }

    private static function problem(array $widget, string $message): string
    {
        return '<div class="b-w b-problem"'.self::attributes($widget, true).' role="note">'.e($message).'</div>';
    }

    private static function ordered(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'ordered'], $value);
        }
        $order = array_flip(self::KEY_ORDER);
        $keys = array_keys($value);
        usort($keys, fn ($a, $b) => [$order[$a] ?? 99, array_search($a, array_keys($value), true)] <=> [$order[$b] ?? 99, array_search($b, array_keys($value), true)]);
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = self::ordered($value[$key]);
        }

        return $out;
    }
}
