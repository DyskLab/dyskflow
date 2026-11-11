<?php

namespace App\Support\Builder;

use App\Models\Post;
use App\Models\Term;
use App\Support\Landing;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;

/**
 * Theme Builder templates, like Elementor's: builder layouts that replace a part of the theme.
 * Each is a posts row of type "template". The main template of a kind has the kind as its slug;
 * more of the same kind are "<kind>--<suffix>" and are limited by conditions (posts.conditions),
 * such as one category or one page. Published means active; a draft or a missing template leaves
 * the theme's own view in place.
 *
 * Which template is used: the site controller tags each page (where()), for example
 * ["articles", "post:4", "category:2"]. The first active template whose conditions name one of
 * those tags wins; a template without conditions is used everywhere else.
 */
class Templates
{
    /** kind => the widgets "context" it offers (see widget.json) */
    public const KINDS = ['single' => 'single', 'archive' => 'archive', 'taxonomy' => 'archive', 'header' => 'site', 'footer' => 'site'];

    /** Header and footer are parts of other pages, never pages of their own. */
    public const PARTS = ['header', 'footer'];

    private static array $cache = [];

    private static ?int $cacheFor = null;

    /** @return array<string, array{label: string, description: string}> */
    public static function kinds(): array
    {
        return [
            'single' => ['label' => __('Single article'), 'description' => __('How one article looks: title, image, content, tags, related articles and comments.')],
            'archive' => ['label' => __('Article list'), 'description' => __('The article list, the homepage list and search results.')],
            'taxonomy' => ['label' => __('Category and tag'), 'description' => __('Category and tag archives. Without it they use the article list template.')],
            'header' => ['label' => __('Header'), 'description' => __('The site header on every page that uses the site layout.')],
            'footer' => ['label' => __('Footer'), 'description' => __('The site footer on every page that uses the site layout.')],
        ];
    }

    /** The main template of a kind (its slug is the kind). */
    public static function find(string $kind): ?Post
    {
        return isset(self::KINDS[$kind]) ? Post::where('type', 'template')->where('slug', $kind)->first() : null;
    }

    /** The kind of a template row: its slug before "--". */
    public static function kindOf(Post|string $template): string
    {
        return explode('--', $template instanceof Post ? (string) $template->slug : $template, 2)[0];
    }

    /** A free slug for one more template of a kind. */
    public static function newSlug(string $kind): string
    {
        if (! Post::withTrashed()->where('type', 'template')->where('slug', $kind)->exists()) {
            return $kind;
        }
        do {
            $slug = $kind.'--'.strtolower(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (Post::withTrashed()->where('type', 'template')->where('slug', $slug)->exists());

        return $slug;
    }

    /** Tags the page being shown, so templates with conditions can be matched. See the class comment. */
    public static function where(array $tags): void
    {
        request()->attributes->set('template_where', array_values(array_unique(array_map('strval', $tags))));
    }

    /** @return list<string> the tags of the page being shown */
    public static function tags(): array
    {
        return (array) request()->attributes->get('template_where', []);
    }

    /** The published layout of a kind for the page being shown, or null when the theme's view is used. */
    public static function live(string $kind): ?array
    {
        $tags = self::tags();
        foreach (self::active($kind) as $template) {
            $conditions = (array) ($template['conditions'] ?? []);
            if (! $conditions || array_intersect($conditions, $tags)) {
                return $template['spec'];
            }
        }

        return null;
    }

    /**
     * The active templates of a kind, read once per request: those with conditions first, then the
     * main one, then the others.
     *
     * @return list<array{conditions: list<string>, spec: array}>
     */
    private static function active(string $kind): array
    {
        if (self::$cacheFor !== spl_object_id(request())) {
            self::$cache = [];
            self::$cacheFor = spl_object_id(request());
        }
        if (! array_key_exists($kind, self::$cache)) {
            $posts = rescue(fn () => Post::where('type', 'template')
                ->where(fn ($q) => $q->where('slug', $kind)->orWhere('slug', 'like', $kind.'--%'))
                ->where('status', 'published')->whereNotNull('builder')->orderBy('id')->get(), collect(), false);
            self::$cache[$kind] = $posts
                ->sortBy(fn (Post $p) => [empty($p->conditions) ? 1 : 0, $p->slug === $kind ? 0 : 1, $p->id])
                ->map(fn (Post $p) => ['conditions' => array_values((array) $p->conditions), 'spec' => $p->builderSpec()])
                ->filter(fn ($t) => $t['spec'] !== null)
                ->values()->all();
        }

        return self::$cache[$kind];
    }

    /**
     * What a template of this kind can be limited to, as condition => label.
     *
     * @return array<string, string>
     */
    public static function conditionOptions(string $kind): array
    {
        $terms = fn (string $taxonomy, string $label) => Term::where('taxonomy', $taxonomy)->orderBy('name')->limit(300)->get(['id', 'name'])
            ->mapWithKeys(fn ($t) => [$taxonomy.':'.$t->id => $label.': '.$t->name])->all();

        return match ($kind) {
            'single' => $terms('category', __('Category')) + $terms('tag', __('Tag')),
            'archive' => ['home' => __('Homepage list'), 'blog' => __('All articles page'), 'search' => __('Search results')],
            'taxonomy' => ['taxonomy:category' => __('All categories'), 'taxonomy:tag' => __('All tags')] + $terms('category', __('Category')) + $terms('tag', __('Tag')),
            default => [
                'home' => __('Homepage'),
                'articles' => __('All articles'),
                'lists' => __('Article lists, categories and search'),
                'pages' => __('All pages'),
            ]
                + Post::where('type', 'page')->orderBy('title')->limit(300)->get(['id', 'title'])->mapWithKeys(fn ($p) => ['page:'.$p->id => __('Page').': '.$p->title])->all()
                + Landing::all()->mapWithKeys(fn ($l) => ['landing:'.$l['slug'] => __('Landing page').': '.__($l['title'])])->all(),
        };
    }

    /**
     * The same choices as conditionOptions(), in groups with short labels, for the Conditions form.
     *
     * @return list<array{label: string, options: array<string, array{label: string, description?: string}>}>
     */
    public static function conditionGroups(string $kind): array
    {
        $terms = fn (string $taxonomy) => Term::where('taxonomy', $taxonomy)->orderBy('name')->limit(300)->get(['id', 'name'])
            ->mapWithKeys(fn ($t) => [$taxonomy.':'.$t->id => ['label' => $t->name]])->all();
        $general = fn (array $options) => array_map(fn ($o) => ['label' => $o[0], 'description' => $o[1]], $options);

        $groups = match ($kind) {
            'single' => [__('Category') => $terms('category'), __('Tag') => $terms('tag')],
            'archive' => [__('Where') => $general([
                'home' => [__('Homepage list'), __('When the homepage shows the latest articles.')],
                'blog' => [__('All articles page'), '/blog'],
                'search' => [__('Search results'), '/search'],
            ])],
            'taxonomy' => [
                __('Where') => $general(['taxonomy:category' => [__('All categories'), '/category/…'], 'taxonomy:tag' => [__('All tags'), '/tag/…']]),
                __('Category') => $terms('category'),
                __('Tag') => $terms('tag'),
            ],
            default => [
                __('Where') => $general([
                    'home' => [__('Homepage'), __('Whatever the homepage shows.')],
                    'articles' => [__('All articles'), __('Each article page.')],
                    'lists' => [__('Article lists, categories and search'), '/blog, /category/…, /search'],
                    'pages' => [__('All pages'), __('Pages, including builder pages.')],
                ]),
                __('Page') => Post::where('type', 'page')->orderBy('title')->limit(300)->get(['id', 'title', 'slug'])
                    ->mapWithKeys(fn ($p) => ['page:'.$p->id => ['label' => $p->title, 'description' => '/'.$p->slug]])->all(),
                __('Landing page') => Landing::all()->mapWithKeys(fn ($l) => ['landing:'.$l['slug'] => ['label' => __($l['title']), 'description' => 'landing/'.$l['slug']]])->all(),
            ],
        };

        return collect($groups)->filter()->map(fn ($options, $label) => ['label' => $label, 'options' => $options])->values()->all();
    }

    /** Where a template is used, in words. */
    public static function describe(Post $template): string
    {
        $conditions = array_values((array) $template->conditions);
        if (! $conditions) {
            return __('Everywhere');
        }
        $options = self::conditionOptions(self::kindOf($template));

        return implode(', ', array_map(fn ($c) => $options[$c] ?? $c, $conditions));
    }

    public static function flush(): void
    {
        self::$cache = [];
        self::$cacheFor = null;
    }

    /** True when the header or footer is a template, so the theme layout loads builder.css. */
    public static function hasParts(): bool
    {
        return self::live('header') !== null || self::live('footer') !== null;
    }

    /**
     * A whole page from a template, with the theme's SEO tags, or null to use the theme's view.
     * $context is what the dynamic widgets show (Context).
     */
    public static function render(string $kind, array $context, array $seo): ?Response
    {
        $spec = self::live($kind) ?? ($kind === 'taxonomy' ? self::live('archive') : null);
        if (! $spec) {
            return null;
        }
        Context::set($context);
        try {
            $tags = view('site.partials.seo', ['seo' => $seo])->render().view('site.partials.head-hooks')->render();

            return response(Builder::render($spec, $tags, $seo));
        } finally {
            Context::reset();
        }
    }

    /** The header or footer template's markup with its CSS, or null to use the theme's partial. */
    public static function part(string $kind, ?array $languageLink = null, ?array $spec = null): ?HtmlString
    {
        $spec ??= self::live($kind);
        if (! $spec || ! in_array($kind, self::PARTS, true)) {
            return null;
        }
        $previous = Context::$languageLink;
        Context::$languageLink = $languageLink;
        try {
            [$html, $css] = Builder::body($spec['sections'] ?? []);
        } finally {
            Context::$languageLink = $previous;
        }
        $css .= Builder::css($spec['css'] ?? '');
        $js = Builder::script($spec['js'] ?? '');

        // Landing pages include the header and footer without loading builder.css themselves. Link it
        // once only: a second link after the header's own <style> would override the header's values.
        $link = self::linkCss();

        return new HtmlString($link.($css !== '' ? '<style>'.$css.'</style>' : '')
            .'<'.$kind.' class="b-site-'.$kind.'">'.$html.'</'.$kind.'>'
            .($js !== '' ? '<script>'.$js.'</script>' : ''));
    }

    /** The <link> to builder.css the first time it is asked for in a request, then an empty string. */
    public static function linkCss(): string
    {
        if (request()->attributes->get('builder_css_linked')) {
            return '';
        }
        request()->attributes->set('builder_css_linked', true);

        return '<link rel="stylesheet" href="'.e(asset_v('css/builder.css')).'">';
    }

    /** SEO for the editor preview of a template: never indexed. */
    public static function previewSeo(string $title): array
    {
        return Seo::defaults(['title' => $title, 'description' => null, 'noindex' => true]);
    }

    /** What a new template starts with: the theme's look, built from containers and dynamic widgets. */
    public static function starter(string $kind): array
    {
        $x = fn (array $style, array $children) => ['id' => Builder::newId('x'), 'type' => 'container', 'style' => $style, 'children' => $children];
        $w = fn (string $type, array $props = []) => ['id' => Builder::newId('w'), 'type' => $type] + $props;

        $sections = match ($kind) {
            // As the theme: logo and menu on the left, search and switches on the right.
            'header' => [$x(['direction' => 'row', 'justify' => 'between', 'align' => 'center', 'wrap' => 'wrap', 'gap' => '20px', 'padding' => ['base' => '10px 24px', 'mobile' => '10px 16px'], 'tag' => 'div', 'boxed_width' => '1120px'], [
                $x(['direction' => 'row', 'align' => 'center', 'wrap' => 'wrap', 'gap' => '20px', 'width' => 'auto'], [$w('site-logo'), $w('site-menu')]),
                $w('site-tools'),
            ])],
            'footer' => [$x(['padding' => ['base' => '24px', 'mobile' => '24px 16px'], 'tag' => 'div', 'boxed_width' => '1120px'], [$w('site-copyright')])],
            'single' => [
                $x(['boxed_width' => '760px', 'gap' => '24px', 'padding' => '40px 24px'], [
                    $w('post-title'), $w('post-meta'), $w('post-image'), $w('post-content'), $w('post-tags'),
                ]),
                $x(['boxed_width' => '760px', 'padding' => '0 24px 40px'], [
                    $w('heading', ['tag' => 'h2', 'size' => 'md', 'text' => ['id' => 'Artikel terkait', 'en' => 'Related articles']]),
                    $w('related-posts'),
                    $w('post-comments'),
                ]),
            ],
            default => [$x(['boxed_width' => '1120px', 'gap' => '24px', 'padding' => '40px 24px'], [
                $w('archive-title'), $w('archive-posts'), $w('archive-pagination'),
            ])],
        };

        return ['version' => Builder::VERSION, 'layout' => in_array($kind, self::PARTS, true) ? 'blank' : 'site', 'sections' => $sections];
    }
}
