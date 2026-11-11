<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Term;
use App\Support\Builder\Builder;
use App\Support\Builder\Templates;
use App\Support\ContentImages;
use App\Support\HtmlSanitizer;
use App\Support\Landing;
use App\Support\Locales;
use App\Support\Seo;
use App\Support\Theme;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteController extends Controller
{
    private function perPage(): int
    {
        return max(1, (int) setting('posts_per_page', 10));
    }

    /** SiteLocale set this from the URL. */
    private function locale(): string
    {
        return app()->getLocale();
    }

    private function articles()
    {
        $locale = $this->locale();

        return Post::where('type', 'article')->liveIn($locale)
            ->with(Locales::isDefault($locale) ? ['categories', 'author'] : ['categories.translations', 'author', 'translations'])
            ->orderByDesc('published_at');
    }

    /** A page past the end does not exist, so it must not answer 200. */
    private function listing($query)
    {
        $posts = $query->paginate($this->perPage());
        abort_if($posts->isEmpty() && $posts->currentPage() > 1, 404);
        $posts->getCollection()->each->localize($this->locale());

        return $posts;
    }

    /** The second language has its own slugs and never falls back to the original: no translation, no page. */
    private function findPost(string $type, string $slug, array $with = []): Post
    {
        $locale = $this->locale();
        $query = Post::where('type', $type)->with($with);
        if (Locales::isDefault($locale)) {
            return $query->where('slug', $slug)->firstOrFail();
        }

        return $query->whereHas('translations', fn ($t) => $t->where('locale', $locale)->where('slug', $slug))->firstOrFail()->localize($locale);
    }

    public function home()
    {
        $locale = $this->locale();
        Templates::where(['home']);
        if (($landing = Landing::home()) && ($response = Landing::render($landing, Seo::forLanding(Landing::find($landing))))) {
            return $response;
        }

        $homeId = setting('home_type', 'landing') === 'page' ? setting('home_page_id') : null;
        if ($homeId && ($page = Post::where('type', 'page')->liveIn($locale)->find($homeId))) {
            return $this->renderPage($page->localize($locale));
        }

        Templates::where(['home', 'lists']);
        $posts = $this->listing($this->articles());
        // A list standing in for a page that has no translation is not a version of that page.
        $standsIn = $homeId && Post::where('type', 'page')->live()->whereKey($homeId)->exists();

        return $this->listView('archive', [
            'posts' => $posts,
            'heading' => setting('site_title', config('app.name')),
            'intro' => Locales::text('tagline'),
            'seo' => Seo::defaults(Seo::paged([
                'title' => Seo::defaults()['title'],
                'canonical' => Locales::url('', $locale),
                'alternates' => $standsIn ? [] : Seo::listVersions(),
            ], $posts)),
        ]);
    }

    /** A list from its Theme Builder template when one is active, or the theme's site.list. */
    private function listView(string $kind, array $data)
    {
        return Templates::render($kind, ['posts' => $data['posts'], 'heading' => $data['heading'], 'intro' => $data['intro'] ?? null, 'term' => $data['term'] ?? null], $data['seo'])
            ?? view('site.list', $data);
    }

    public function blog()
    {
        Templates::where(['blog', 'lists']);
        $posts = $this->listing($this->articles());

        return $this->listView('archive', [
            'posts' => $posts,
            'heading' => __('Articles'),
            'seo' => Seo::defaults(Seo::paged([
                'title' => Seo::formatTitle(__('Articles')),
                'description' => __('All articles on :site.', ['site' => setting('site_title', config('app.name'))]),
                'canonical' => site_route('blog'),
                'alternates' => Seo::listVersions('blog'),
            ], $posts)),
        ]);
    }

    public function article(string $slug)
    {
        $post = $this->findPost('article', $slug, ['categories.translations', 'tags.translations', 'author']);
        $this->ensureVisible($post);
        $this->countView($post);
        Templates::where(['articles', 'post:'.$post->id, ...$post->terms()->get(['terms.id', 'terms.taxonomy'])->map(fn ($t) => $t->taxonomy.':'.$t->id)]);

        $data = [
            'post' => $post,
            'seo' => Seo::forPost($post),
            'content' => ContentImages::enhance(HtmlSanitizer::clean(apply_filters('cms.content', (string) $post->content, $post))),
            'comments' => $post->comments()->where('status', 'approved')->oldest()->limit((int) setting('comments_per_page', 50))->get(),
        ];

        return Templates::render('single', array_diff_key($data, ['seo' => 1]), $data['seo']) ?? view('site.article', $data);
    }

    public function page(string $slug)
    {
        try {
            $post = $this->findPost('page', $slug);
        } catch (ModelNotFoundException $e) {
            return $this->landingAt($slug) ?? throw $e;
        }
        $this->ensureVisible($post);

        // One address per page: a page used as the homepage lives at the root of its language.
        if ($post->isHomePage()) {
            return redirect(Locales::url('', $this->locale()), 301);
        }

        return $this->renderPage($post);
    }

    /** A landing page with "public": true in landing.json has its own address, unless a page already uses it. */
    private function landingAt(string $slug)
    {
        if (Landing::home() === $slug) {
            return redirect(Locales::url('', $this->locale()), 301);
        }
        $landing = Landing::find($slug);

        return $landing && $landing['public'] ? Landing::render($slug, Seo::forLanding($landing, $slug)) : null;
    }

    private function renderPage(Post $page)
    {
        $this->countView($page);
        Templates::where([...Templates::tags(), 'pages', 'page:'.$page->id]);

        if ($page->isBuilder()) {
            $seo = Seo::forPost($page);
            $tags = view('site.partials.seo', ['seo' => $seo])->render().view('site.partials.head-hooks')->render();

            return response(Builder::render($page->builderSpec() ?? Builder::blank(), $tags, $seo));
        }

        return view('site.page', [
            'post' => $page,
            'seo' => Seo::forPost($page),
            'content' => ContentImages::enhance(HtmlSanitizer::clean(apply_filters('cms.content', (string) $page->content, $page))),
        ]);
    }

    public function term(Request $request, string $slug)
    {
        $taxonomy = $request->route('taxonomy');
        $locale = $this->locale();
        $term = Term::where('taxonomy', $taxonomy)
            ->when(
                Locales::isDefault($locale),
                fn ($q) => $q->where('slug', $slug),
                fn ($q) => $q->whereHas('translations', fn ($t) => $t->where('locale', $locale)->where('slug', $slug)),
            )
            ->firstOrFail()
            ->localize($locale);
        Templates::where(['lists', 'taxonomy:'.$taxonomy, $taxonomy.':'.$term->id]);
        $posts = $this->listing($this->articles()->whereHas('terms', fn ($q) => $q->where('terms.id', $term->id)));

        return $this->listView('taxonomy', [
            'posts' => $posts,
            'term' => $term,
            'heading' => $term->name,
            'intro' => $term->description,
            'seo' => Seo::paged(Seo::forTerm($term), $posts),
        ]);
    }

    public function search(Request $request)
    {
        $term = trim(Str::limit(is_string($request->query('q')) ? $request->query('q') : '', 100, ''));
        $like = '%'.addcslashes($term, '%_\\').'%';
        $matches = fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('content', 'like', $like));
        $locale = $this->locale();
        Templates::where(['search', 'lists']);

        return $this->listView('archive', [
            'posts' => $this->listing($this->articles()
                ->when(
                    $term !== '',
                    fn ($q) => Locales::isDefault($locale) ? $matches($q) : $q->whereHas('translations', fn ($t) => $matches($t->where('locale', $locale))),
                    fn ($q) => $q->whereRaw('1 = 0'),
                ))
                ->withQueryString(),
            'heading' => __('Search: :term', ['term' => $term]),
            'seo' => Seo::defaults(['title' => Seo::formatTitle(__('Search')), 'description' => null, 'noindex' => true]),
        ]);
    }

    public function comment(Request $request, string $slug)
    {
        // Comments belong to the original, so every language shows the same ones.
        $post = $this->findPost('article', $slug);
        abort_unless($post->isLive(), 404);
        abort_unless($post->commentsOpen(), 403);

        // Bots fill this hidden field. Pretend it worked and store nothing.
        if ($request->filled('website_confirm')) {
            return back()->with('success', __('Thank you. Your comment was received.'));
        }

        $data = $request->validate([
            'author_name' => 'required|string|max:100',
            'author_email' => [setting('comments_require_email', '1') ? 'required' : 'nullable', 'email', 'max:190'],
            'author_url' => 'nullable|url:http,https|max:190',
            'content' => 'required|string|min:2|max:5000',
        ]);

        $blocked = collect(preg_split('/\R/', (string) setting('comments_blocklist')))->map(fn ($w) => trim($w))->filter();
        $text = $data['author_name'].' '.$data['content'];
        $status = match (true) {
            $blocked->contains(fn ($word) => stripos($text, $word) !== false) => 'spam',
            (bool) setting('comments_moderation', '1') => 'pending',
            default => 'approved',
        };

        $comment = $post->comments()->create($data + [
            'status' => $status,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
        do_action('comment.created', $comment);

        return back()->with('success', $status === 'approved' ? __('Your comment was published.') : __('Thank you. Your comment is waiting for approval.'));
    }

    public function sitemap()
    {
        abort_unless(setting('seo_sitemap', '1'), 404);

        // Keyed by URL, so an address listed twice (the homepage page) appears once.
        $entries = [];
        $add = function (array $versions, $lastmod = null) use (&$entries) {
            foreach ($versions as $url) {
                $entries[$url] = ['loc' => $url, 'lastmod' => $lastmod, 'links' => Seo::hreflangSet($versions)];
            }
        };

        $home = setting('home_type', 'landing') === 'page' ? Post::where('type', 'page')->live()->with('translations')->find(setting('home_page_id')) : null;
        if ($home) {
            $add($home->versions());
            // Without a translation of that page, the second language shows its articles there instead.
            if (! $home->translations->contains(fn ($t) => $t->locale === Locales::second() && $t->status === 'published')) {
                $add(array_intersect_key(Seo::listVersions(), [Locales::second() => true]));
            }
        } elseif (Landing::home()) {
            $add(Seo::landingVersions());
        } else {
            $add(Seo::listVersions());
        }
        $add(Seo::listVersions('blog'));
        Landing::all()->where('public', true)->reject(fn ($l) => $l['slug'] === Landing::home())
            ->each(fn ($l) => $add(Seo::landingVersions($l['slug'])));

        Post::live()->with('translations')->orderByDesc('updated_at')
            ->get(['id', 'type', 'slug', 'status', 'noindex', 'canonical_url', 'published_at', 'updated_at'])
            ->each(fn ($post) => $add($post->versions(), $post->updated_at));
        Term::has('posts')->with('translations')->get()->each(fn ($term) => $add($term->versions()));

        return response()->view('site.sitemap', ['entries' => $entries], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $lines = ['User-agent: *', setting('seo_noindex') ? 'Disallow: /' : 'Disallow: /admin'];
        if ($extra = trim((string) setting('seo_robots'))) {
            $lines[] = $extra;
        }
        if (setting('seo_sitemap', '1')) {
            $lines[] = 'Sitemap: '.url('sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function feed()
    {
        return response()->view('site.feed', ['posts' => $this->articles()->limit(20)->get()->each->localize($this->locale())], 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    public function themeAsset(string $path)
    {
        return Theme::assetResponse($path);
    }

    public function landingAsset(string $slug, string $path)
    {
        $directory = Landing::directory($slug) ?? abort(404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(isset(Landing::ASSET_TYPES[$extension]), 404);

        // realpath resolves "..", so a request cannot leave the landing folder.
        $file = realpath($directory.'/'.$path);
        abort_unless($file && str_starts_with($file, realpath($directory).DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, [
            'Content-Type' => Landing::ASSET_TYPES[$extension],
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * One visit by someone who is not logged in. Bots and link previews do not count.
     * The query builder keeps updated_at, which the sitemap uses as the last change, and a failure here never stops the page.
     */
    private function countView(Post $post): void
    {
        $request = request();
        if (! $request->isMethod('GET') || auth()->check() || ! $post->isLive() || $request->header('Sec-Purpose') || $request->header('Purpose')) {
            return;
        }
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|lighthouse|headless|curl|wget|python/i', $agent = (string) $request->userAgent()) || $agent === '') {
            return;
        }

        rescue(function () use ($post) {
            DB::table('posts')->where('id', $post->id)->increment('view_count');
            $post->view_count++;
        }, report: false);
    }

    /** Drafts and scheduled posts are visible only to logged-in staff, for previewing. */
    private function ensureVisible(Post $post): void
    {
        abort_unless($post->isLive() || auth()->check(), 404);
    }
}
