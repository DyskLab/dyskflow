<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Term;
use Illuminate\Pagination\LengthAwarePaginator;

/** Builds the values rendered by site/partials/seo.blade.php. */
class Seo
{
    public static function defaults(array $overrides = []): array
    {
        $site = setting('site_title', config('app.name'));
        $tagline = Locales::text('tagline');

        $seo = array_merge([
            'title' => $tagline ? $site.' — '.$tagline : $site,
            'description' => Locales::text('seo_description') ?: $tagline,
            'canonical' => url()->current(),
            'image' => cms_asset_url(setting('seo_og_image')),
            'noindex' => (bool) setting('seo_noindex', '0'),
            'type' => 'website',
            'schema' => null,
            'alternates' => [], // indexable versions by language, for hreflang
        ], $overrides);
        $seo['languages'] ??= $seo['alternates']; // every public version by language, for the language link

        return $seo;
    }

    public static function formatTitle(string $title): string
    {
        return str_replace(
            ['%title%', '%site%'],
            [$title, setting('site_title', config('app.name'))],
            setting('seo_title_format', '%title% — %site%'),
        );
    }

    public static function forPost(Post $post): array
    {
        $description = $post->meta_description ?: ($post->excerpt ?: cms_excerpt($post->content));
        $image = cms_asset_url($post->og_image ?: $post->featured_image);

        $seo = [
            'title' => $post->meta_title ?: self::formatTitle($post->title),
            'description' => $description ?: Locales::text('seo_description'),
            'canonical' => $post->canonical_url ?: $post->url(),
            'noindex' => $post->noindex || (bool) setting('seo_noindex', '0'),
            'type' => $post->type === 'article' ? 'article' : 'website',
            'alternates' => $post->versions(),
            'languages' => $post->versions(false),
        ];
        if ($image) {
            $seo['image'] = $image;
        }
        if ($post->type === 'article') {
            $seo['schema'] = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $post->title,
                'description' => $description,
                'image' => $image,
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->updated_at?->toIso8601String(),
                'author' => ['@type' => 'Person', 'name' => $post->author?->name],
                'inLanguage' => app()->getLocale(),
                'mainEntityOfPage' => $post->url(),
            ];
        }

        return self::defaults($seo);
    }

    public static function forTerm(Term $term): array
    {
        return self::defaults([
            'title' => $term->meta_title ?: self::formatTitle($term->name),
            'description' => $term->meta_description ?: ($term->description ?: __('Articles about :name on :site.', ['name' => $term->name, 'site' => setting('site_title', config('app.name'))])),
            'canonical' => $term->url(),
            'alternates' => $term->versions(),
        ]);
    }

    /** Each page of a list is its own address, with its own canonical and title. Only the first has language versions. */
    public static function paged(array $seo, LengthAwarePaginator $posts): array
    {
        $seo['languages'] ??= $seo['alternates'] ?? [];

        // An empty list in the second language has nothing worth indexing.
        if ($posts->total() === 0 && ! Locales::isDefault(app()->getLocale())) {
            $seo['noindex'] = true;
        }

        $page = $posts->currentPage();
        if ($page > 1) {
            $base = $seo['canonical'] ?? url()->current();
            // The homepage URL has no path, and a query needs one: site.test/?page=2.
            $seo['canonical'] = $base.(parse_url($base, PHP_URL_PATH) ? '' : '/').'?page='.$page;
            $seo['title'] .= ' — '.__('Page :number', ['number' => $page]);
            unset($seo['alternates']);
        }

        return $seo;
    }

    /**
     * Versions of a list of articles, such as the blog. The second language counts only with articles to show.
     *
     * @return array<string, string>
     */
    public static function listVersions(string $path = ''): array
    {
        $second = Locales::second();
        $versions = [Locales::default() => Locales::url($path)];
        if (Post::where('type', 'article')->liveIn($second)->exists()) {
            $versions[$second] = Locales::url($path, $second);
        }

        return $versions;
    }

    /**
     * A landing page serves every language from one folder, at the same path.
     *
     * @return array<string, string>
     */
    public static function landingVersions(string $path = ''): array
    {
        return [Locales::default() => Locales::url($path), Locales::second() => Locales::url($path, Locales::second())];
    }

    public static function forLanding(array $landing, string $path = ''): array
    {
        $versions = self::landingVersions($path);

        return self::defaults(($path === '' ? [
            'description' => Locales::text('seo_description') ?: ($landing['description'] ?: Locales::text('tagline')),
        ] : [
            'title' => self::formatTitle(__($landing['title'])),
            'description' => $landing['description'] ? __($landing['description']) : Locales::text('seo_description'),
        ]) + [
            'canonical' => $versions[app()->getLocale()] ?? Locales::url($path, app()->getLocale()),
            'alternates' => $versions,
        ]);
    }

    /**
     * The hreflang tags of a page. The set is the same on each version, which makes the tags reciprocal.
     * A page outside its own set (hidden, or with its canonical elsewhere) prints none.
     *
     * @return array<string, string>
     */
    public static function hreflang(array $seo): array
    {
        $versions = $seo['alternates'] ?? [];
        if (! empty($seo['noindex']) || ($versions[app()->getLocale()] ?? null) !== $seo['canonical']) {
            return [];
        }

        return self::hreflangSet($versions);
    }

    /**
     * A language annotation needs a second version to point to. x-default is the default language.
     *
     * @return array<string, string>
     */
    public static function hreflangSet(array $versions): array
    {
        return count($versions) < 2 ? [] : $versions + ['x-default' => $versions[Locales::default()]];
    }

    /**
     * Where the language link goes: the same page when it exists there, otherwise that language's homepage.
     * Returns null when the other language has nothing to show.
     *
     * @return array{locale: string, url: string}|null
     */
    public static function languageLink(array $seo): ?array
    {
        $other = Locales::isDefault(app()->getLocale()) ? Locales::second() : Locales::default();
        $url = $seo['languages'][$other] ?? null;
        if (! $url && (Locales::isDefault($other) || Post::liveIn($other)->exists())) {
            $url = Locales::url('', $other);
        }

        return $url ? ['locale' => $other, 'url' => $url] : null;
    }
}
