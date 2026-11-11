<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FileRevision;
use App\Models\Post;
use App\Models\Term;
use App\Support\Builder\Box;
use App\Support\Builder\Builder;
use App\Support\Builder\Context;
use App\Support\Builder\Templates;
use App\Support\Builder\Tokens;
use App\Support\Builder\Validator;
use App\Support\Builder\Widgets;
use App\Support\ContentImages;
use App\Support\DataTable;
use App\Support\HtmlSanitizer;
use App\Support\Locales;
use App\Support\MediaUploader;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The visual builder for pages, like Elementor: the layout is JSON in posts.builder, and the editor
 * saves unpublished changes to posts.builder_draft. Publish copies the draft to posts.builder,
 * publishes the page and keeps the layout it replaced as a revision.
 */
class BuilderController extends Controller
{
    /** Revisions of a page layout share the file_revisions table under this path. */
    public static function revisionPath(Post $post): string
    {
        return 'page:'.$post->id.'/builder.json';
    }

    public function createForm()
    {
        return view('admin.partials.sheet-form', [
            'title' => __('New builder page'),
            'description' => __('A page you design visually. Title, address, SEO and the translation are set like any other page.'),
            'action' => route('admin.pages.builder.store'),
            'values' => [],
            'fields' => [
                ['name' => 'title', 'label' => __('Title'), 'required' => true, 'maxlength' => 255],
                ['name' => 'template', 'label' => __('Start from'), 'type' => 'starters', 'options' => self::starterCards(), 'default' => 'blank'],
            ],
            'submit' => __('Create'),
        ]);
    }

    /** A starting point drawn as a page, for the thumbnails in "New builder page". Never saved. */
    public function starter(Request $request)
    {
        $from = (string) $request->query('from', 'blank');
        abort_unless(array_key_exists($from, self::templates()), 404);
        $spec = Validator::check(self::startSpec($from))['spec'];
        $seo = Templates::previewSeo(__('Preview'));
        $tags = view('site.partials.seo', ['seo' => ['noindex' => true] + $seo])->render();

        return response(Builder::render($spec, $tags, $seo))
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'private, max-age=300');
    }

    /** The layout a new page starts with: empty, a ready-made template, or a copy of a builder page. */
    private static function startSpec(string $from): array
    {
        $source = str_starts_with($from, 'page:') ? Post::where('type', 'page')->find((int) substr($from, 5)) : null;

        return match (true) {
            $source !== null => $source->builderSpec(true),
            str_starts_with($from, 'template:') => Builder::template(substr($from, 9)),
            default => null,
        } ?? Builder::blank();
    }

    /** @return array<string, array{title: string, description: string, preview: string}> */
    private static function starterCards(): array
    {
        $cards = ['blank' => ['title' => __('Empty page'), 'description' => __('The site header and footer, and an empty space to build in.')]];
        foreach (Builder::templates() as $name => $t) {
            $cards['template:'.$name] = ['title' => __($t['title']), 'description' => __($t['description'])];
        }
        foreach (self::templates() as $key => $label) {
            if (str_starts_with($key, 'page:')) {
                $cards[$key] = ['title' => $label, 'description' => __('The same layout, to change into a new page.')];
            }
        }

        foreach ($cards as $key => $card) {
            $cards[$key]['preview'] = route('admin.pages.builder.starter', ['from' => $key]);
        }

        return $cards;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'template' => 'required|string|in:'.implode(',', array_keys(self::templates())),
        ]);
        $this->throttle();

        $source = str_starts_with($data['template'], 'page:') ? Post::where('type', 'page')->find((int) substr($data['template'], 5)) : null;
        $spec = self::startSpec($data['template']);
        $spec = Validator::check(self::freshIds($spec))['spec'];
        try {
            Builder::guardCode($source, $spec);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['template' => $e->getMessage()]);
        }

        $post = Post::create([
            'type' => 'page',
            'user_id' => auth()->id(),
            'title' => $data['title'],
            'slug' => self::uniqueSlug($data['title']),
            'content' => '',
            'status' => 'draft',
            'allow_comments' => false,
            'builder' => Builder::compact(Builder::blank($spec['layout'] ?? 'site')),
            'builder_draft' => Builder::compact($spec),
        ]);
        activity('page.created', $post->title);

        return redirect()->route('admin.pages.builder.edit', $post)->with('success', __('Page created.'));
    }

    /**
     * A classic page moves to the builder. Its content becomes a text widget, published right away,
     * so a live page looks the same until the new layout is published.
     */
    public function start(Post $post): RedirectResponse
    {
        $this->page($post, false);
        abort_if($post->type !== 'page', 404);
        if (! $post->isBuilder()) {
            $post->forceFill(['builder' => Builder::compact(Builder::fromContent($post->content)), 'builder_draft' => null])->save();
            activity('page.builder', $post->title);
        }

        return redirect(self::to($post, 'edit'));
    }

    /** Back to the classic editor. The layout is kept as a revision; the content keeps the last published text. */
    public function classic(Post $post): RedirectResponse
    {
        $this->page($post);
        abort_if($post->type !== 'page', 404);
        self::record($post, $post->builderSpec(true));
        $post->forceFill(['builder' => null, 'builder_draft' => null])->save();
        activity('page.classic', $post->title);

        return redirect()->route('admin.pages.edit', $post)->with('success', __('The page uses the classic editor again. Its layout is kept as a revision.'));
    }

    public function edit(Post $post)
    {
        $this->page($post);

        $template = $post->type === 'template' ? Templates::kindOf($post) : null;

        return view('admin.builder', [
            'title' => $post->title,
            'post' => $post,
            'url' => fn (string $name, array $params = []) => self::to($post, $name, $params),
            'exitUrl' => match ($post->type) {
                'template' => route('admin.design.templates.index'),
                'block' => route('admin.design.blocks.index'),
                default => route('admin.pages.edit', $post),
            },
            'template' => $template,
            'boot' => $this->state($post),
            'locales' => collect(Locales::ALL)->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values(),
            'defaultLocale' => Locales::default(),
        ]);
    }

    /** The draft (or the published layout), its hash, and everything the editor needs to build its panels. */
    public function spec(Post $post): JsonResponse
    {
        $this->page($post);

        return response()->json($this->state($post));
    }

    public function saveDraft(Request $request, Post $post): JsonResponse
    {
        $this->page($post);
        $data = $request->validate(['spec' => 'required|array', 'hash' => 'nullable|string|size:64', 'force' => 'boolean']);
        $result = Validator::check($data['spec']);
        // The hash is of the layout the editor loaded: the draft, or the published one when there was no draft.
        if (empty($data['force']) && ($data['hash'] ?? null) !== null && $data['hash'] !== self::hash($post)) {
            return response()->json(['message' => __('The page was changed elsewhere, for example by an AI tool or in another tab.'), 'conflict' => true], 409);
        }
        try {
            Builder::guardCode($post, $result['spec'], BlockController::specs());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $post->forceFill(['builder_draft' => Builder::compact($result['spec'])])->save();

        return response()->json(['message' => __('Draft saved.'), 'hash' => self::hash($post), 'spec' => $result['spec'], 'errors' => $result['errors'], 'warnings' => $result['warnings']]);
    }

    /** HTML for the preview, from the layout in the editor (which may not be saved yet). */
    public function render(Request $request, Post $post)
    {
        $this->page($post);
        $data = $request->validate(['spec' => 'required|array', 'locale' => 'nullable|string']);
        $locale = isset(Locales::ALL[$data['locale'] ?? '']) ? $data['locale'] : Locales::default();
        app()->setLocale($locale);
        $spec = Validator::check($data['spec'])['spec'];

        if ($post->type === 'template') {
            $kind = Templates::kindOf($post);
            $seo = Templates::previewSeo($post->title);
            if (in_array($kind, Templates::PARTS, true)) {
                $spec['layout'] = 'blank';
            }
            Context::set(self::sample($kind, $locale));
        } elseif ($post->type === 'block') {
            $seo = Templates::previewSeo($post->title);
        } else {
            $post->load('translations')->localize($locale);
            $seo = Seo::forPost($post);
        }
        $tags = view('site.partials.seo', ['seo' => ['noindex' => true] + $seo])->render();
        try {
            $html = Builder::render($spec, $tags, $seo, true);
        } finally {
            Context::reset();
        }

        return response($html)
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * What a template shows in the editor: the newest article, or the newest articles as a list.
     * Without articles, an example that is never saved.
     */
    private static function sample(string $kind, string $locale): array
    {
        $articles = fn () => Post::where('type', 'article')->liveIn($locale)->with(['categories', 'tags', 'author'])->orderByDesc('published_at');
        if ($kind === 'single') {
            $post = $articles()->first()?->localize($locale);
            if (! $post) {
                $post = new Post(['type' => 'article', 'title' => __('Example article'), 'slug' => 'example', 'content' => '<p>'.e(__('The text of each article shows here.')).'</p>', 'allow_comments' => true]);
                $post->published_at = now();
                $post->setRelation('categories', collect())->setRelation('tags', collect())->setRelation('author', auth()->user());
            }

            return [
                'post' => $post,
                'content' => ContentImages::enhance(HtmlSanitizer::clean((string) $post->content)),
                'comments' => $post->exists ? $post->comments()->where('status', 'approved')->oldest()->limit(20)->get() : collect(),
                'sample' => true,
            ];
        }
        if ($kind === 'archive' || $kind === 'taxonomy') {
            $posts = $articles()->paginate(max(1, (int) setting('posts_per_page', 10)));
            $posts->getCollection()->each->localize($locale);
            $term = $kind === 'taxonomy' ? Term::where('taxonomy', 'category')->first()?->localize($locale) : null;

            return [
                'posts' => $posts,
                'heading' => $kind === 'taxonomy' ? ($term?->name ?? __('Category')) : __('Articles'),
                'intro' => $term?->description,
                'term' => $term,
                'sample' => true,
            ];
        }

        return ['sample' => true];
    }

    public function publish(Request $request, Post $post): JsonResponse
    {
        $this->page($post);
        $hash = $request->input('hash');
        if (is_string($hash) && $hash !== self::hash($post)) {
            return response()->json(['message' => __('The page was changed elsewhere, for example by an AI tool or in another tab.'), 'conflict' => true], 409);
        }
        if ($post->builder_draft === null && $post->status === 'published') {
            return response()->json(['message' => __('There are no unpublished changes.')], 422);
        }
        $result = Validator::check($post->builderSpec(true));
        if ($result['errors']) {
            return response()->json(['message' => __('Fix these problems before publishing:'), 'errors' => $result['errors']], 422);
        }

        self::publishLayout($post, $result['spec']);
        activity('page.published', $post->title);

        return response()->json(['message' => __('Published.')] + $this->state($post->refresh()));
    }

    /**
     * Makes a layout the live one: the replaced layout becomes a revision, the page is published
     * (a scheduled date stays), and "content" gets the text of each language for search and feeds.
     */
    public static function publishLayout(Post $post, array $spec): void
    {
        // An empty layout, such as the one a new page starts with, is not worth a revision.
        $live = $post->builderSpec();
        if ($live && ! empty($live['sections']) && $live !== $spec) {
            self::record($post, $live);
        }
        $post->forceFill([
            'builder' => Builder::compact($spec),
            'builder_draft' => null,
            'status' => 'published',
            'published_at' => $post->published_at ?? now(),
            'content' => self::text($spec, Locales::default()),
        ])->save();
        foreach ($post->translations()->get() as $translation) {
            $translation->update(['content' => self::text($spec, $translation->locale)]);
        }
    }

    /** An image or video for this page, saved in the media library. */
    public function upload(Request $request, Post $post): JsonResponse
    {
        $this->page($post);
        $request->validate(['file' => 'required|file']);
        try {
            $media = MediaUploader::store($request->file('file'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        activity('media.uploaded', $media->filename);

        return response()->json(['path' => $media->path]);
    }

    public function discard(Post $post): RedirectResponse
    {
        $this->page($post);
        if ($post->builder_draft !== null) {
            self::record($post, $post->builderSpec(true));
            $post->forceFill(['builder_draft' => null])->save();
            activity('page.discarded', $post->title);
        }

        return redirect(self::to($post, 'edit'))->with('success', __('Unpublished changes were removed. They are kept as a revision.'));
    }

    public function revisions(Post $post)
    {
        $this->page($post);

        return view('admin.table', [
            'title' => __('Revisions: :name', ['name' => $post->title]),
            'description' => __('The last :count layouts of this page before each publish, discard or restore. Restoring puts one in the draft, so you can check it before publishing.', ['count' => 20]),
            'actions' => [['label' => __('Open the builder'), 'icon' => 'landing', 'url' => self::to($post, 'edit')]],
            'table' => DataTable::make([
                'id' => 'builder-revisions',
                'source' => FileRevision::with('user')->where('path', self::revisionPath($post)),
                'columns' => [
                    ['key' => 'created_at', 'label' => __('Saved before'), 'sort' => true, 'render' => fn ($revision) => cms_date($revision->created_at)],
                    ['key' => 'user_id', 'label' => __('Changed by'), 'render' => fn ($revision) => $revision->user?->name ?? '—'],
                    ['key' => 'size', 'label' => __('Size'), 'sort' => true, 'render' => fn ($revision) => Number::fileSize($revision->size)],
                ],
                'default_sort' => ['created_at', 'desc'],
                'bulk_url' => self::to($post, 'revisions'),
                'row_actions' => fn ($revision) => [
                    ['label' => __('View'), 'icon' => 'page', 'sheet' => self::to($post, 'revision', [$revision->id]), 'wide' => true],
                    ['label' => __('Restore'), 'icon' => 'restore', 'post' => self::to($post, 'restore', [$revision->id]),
                        'confirm' => __('Put this version in the draft? The current draft is kept as a revision.')],
                ],
                'empty' => __('No revisions yet. One is kept each time you publish or discard changes.'),
            ]),
        ]);
    }

    public function revision(Post $post, int $revision)
    {
        $this->page($post);

        return view('admin.design.revision', ['revision' => $this->findRevision($post, $revision), 'name' => $post->title]);
    }

    public function restore(Post $post, int $revision): RedirectResponse
    {
        $this->page($post);
        $result = Validator::check(json_decode($this->findRevision($post, $revision)->content, true));
        try {
            Builder::guardCode($post, $result['spec']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        if ($post->builder_draft !== null) {
            self::record($post, $post->builderSpec(true));
        }
        $post->forceFill(['builder_draft' => Builder::compact($result['spec'])])->save();
        activity('page.restored', $post->title);

        return redirect(self::to($post, 'edit'))->with('success', __('Version restored to the draft. Publish it to make it live.'));
    }

    private function findRevision(Post $post, int $id): FileRevision
    {
        return FileRevision::with('user')->where('path', self::revisionPath($post))->findOrFail($id);
    }

    private function state(Post $post): array
    {
        $result = Validator::check($post->builderSpec(true) ?? Builder::blank());

        return [
            'spec' => $result['spec'],
            'hash' => self::hash($post),
            'draft' => $post->builder_draft !== null,
            'live' => $post->isLive(),
            'viewUrl' => $post->type === 'page' && $post->isLive() ? $post->url() : null,
            'template' => $post->type === 'template' ? Templates::kindOf($post) : null,
            'templateContext' => $post->type === 'template' ? (Templates::KINDS[Templates::kindOf($post)] ?? null) : null,
            'block' => $post->type === 'block',
            'blocks' => route('admin.design.blocks.library'),
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
            'widgets' => array_values(array_map(fn ($w) => self::translateWidget(array_diff_key($w, ['view' => 1])), Widgets::all())),
            'categories' => Term::where('taxonomy', 'category')->orderBy('name')->pluck('name', 'slug'),
            'layout' => $result['spec']['layout'] ?? 'site',
            'code' => (bool) auth()->user()?->can('code.manage'),
            'codeWidgets' => array_keys(Builder::CODE_WIDGETS),
            'tokens' => ['section' => Tokens::SECTION, 'column' => Tokens::COLUMN, 'widget' => Tokens::WIDGET, 'widths' => Tokens::WIDTHS],
            'box' => ['container' => Box::CONTAINER, 'widget' => Box::WIDGET, 'colors' => Box::THEME_COLORS, 'length' => trim(Box::LENGTH, '/'), 'maxDepth' => Builder::MAX_CONTAINER_DEPTH],
        ];
    }

    /** What the editor loaded: the draft, or the published layout when there is no draft. */
    public static function hash(Post $post): string
    {
        return hash('sha256', (string) ($post->builder_draft ?? $post->builder));
    }

    /** The text of a layout in one language, for "content": search, feeds and excerpts read it. */
    public static function text(array $spec, string $locale): string
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);
        try {
            $html = Builder::body($spec['sections'] ?? [])[0];
        } finally {
            app()->setLocale($previous);
        }

        return HtmlSanitizer::clean($html);
    }

    /** Keeps a layout as a revision, the last 20 per page. */
    public static function record(Post $post, ?array $spec): void
    {
        if (! $spec) {
            return;
        }
        $content = Builder::encode($spec);
        FileRevision::create(['path' => self::revisionPath($post), 'content' => $content, 'size' => strlen($content), 'user_id' => auth()->id()]);
        $old = FileRevision::where('path', self::revisionPath($post))->latest('id')->skip(20)->take(200)->pluck('id');
        if ($old->isNotEmpty()) {
            FileRevision::whereIn('id', $old)->delete();
        }
    }

    /** Labels in widget.json are English, like every other UI text. */
    private static function translateWidget(array $widget): array
    {
        foreach (['label', 'description', 'help'] as $key) {
            if (is_string($widget[$key] ?? null) && $widget[$key] !== '') {
                $widget[$key] = __($widget[$key]);
            }
        }
        if (isset($widget['fields'])) {
            $widget['fields'] = array_map(fn ($field) => self::translateWidget($field), $widget['fields']);
        }

        return $widget;
    }

    /** An address of the builder for this page or template. */
    public static function to(Post $post, string $name, array $params = []): string
    {
        $group = match ($post->type) {
            'template' => 'admin.design.templates.builder.',
            'block' => 'admin.design.blocks.builder.',
            default => 'admin.pages.builder.',
        };

        return route($group.$name, [$post, ...$params]);
    }

    /**
     * Only pages under Pages, only templates under Design > Templates (each has its own permission),
     * and with $builder only those that use the builder.
     */
    private function page(Post $post, bool $builder = true): void
    {
        $type = match (true) {
            request()->routeIs('admin.design.templates.*') => 'template',
            request()->routeIs('admin.design.blocks.*') => 'block',
            default => 'page',
        };
        abort_unless($post->type === $type, 404);
        abort_unless(! $builder || $post->isBuilder(), 404);
    }

    /** A copy gets new ids, so revisions and AI edits of the two pages never mix them up. */
    public static function freshIds(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        if (isset($node['id']) && is_string($node['id']) && (isset($node['columns']) || isset($node['widgets']) || isset($node['children']) || isset($node['type']))) {
            $node['id'] = Builder::newId($node['id'][0]);
        }
        foreach (['sections', 'columns', 'widgets', 'children'] as $key) {
            if (is_array($node[$key] ?? null)) {
                $node[$key] = array_map([self::class, 'freshIds'], $node[$key]);
            }
        }

        return $node;
    }

    private static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: Str::lower(Str::random(8));
        $slug = $base;
        for ($i = 2; in_array($slug, (array) config('cms.reserved_slugs'), true) || Post::withTrashed()->where('type', 'page')->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** @return array<string, string> */
    private static function templates(): array
    {
        $templates = collect(Builder::templates())->mapWithKeys(fn ($t, $name) => ['template:'.$name => __($t['title']).' · '.__($t['description'])])->all();
        $copies = Post::where('type', 'page')->whereNotNull('builder')->orderBy('title')->pluck('title', 'id')
            ->mapWithKeys(fn ($title, $id) => ['page:'.$id => __('Copy of :title', ['title' => $title])])->all();

        return ['blank' => __('Empty page')] + $templates + $copies;
    }

    private function throttle(): void
    {
        $key = 'builder:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);
    }
}
