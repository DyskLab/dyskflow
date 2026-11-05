<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use App\Support\DataTable;
use App\Support\HtmlSanitizer;
use App\Support\Landing;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Articles and pages share this controller. The route supplies the type. */
class PostController extends Controller
{
    private function type(Request $request): string
    {
        return $request->route('type');
    }

    private function base(string $type): string
    {
        return $type === 'article' ? 'admin.articles' : 'admin.pages';
    }

    private function canManageAll(string $type): bool
    {
        return $type === 'page' || auth()->user()->can('articles.manage');
    }

    public function index(Request $request)
    {
        $type = $this->type($request);
        $base = $this->base($type);
        $isArticle = $type === 'article';

        $query = Post::query()->where('type', $type)->with($isArticle ? ['author', 'categories', 'translations'] : ['author', 'translations']);
        if (! $this->canManageAll($type)) {
            $query->where('user_id', auth()->id());
        }

        $columns = [
            ['key' => 'title', 'label' => __('Title'), 'sort' => true, 'render' => fn ($p) => new HtmlString(
                '<a href="'.e(route("$base.edit", $p)).'" style="font-weight:500;text-decoration:none">'.e($p->title).'</a><div class="muted small">/'.($isArticle ? 'blog/' : '').e($p->slug).'</div>'
            )],
            ['key' => 'status', 'label' => __('Status'), 'sort' => true, 'render' => fn ($p) => $this->statusBadge($p)],
            ['key' => 'translation', 'label' => Locales::name(Locales::second()), 'render' => fn ($p) => $this->translationBadge($p)],
        ];
        if ($isArticle) {
            $columns[] = ['key' => 'categories', 'label' => __('Categories'), 'render' => fn ($p) => $p->categories->pluck('name')->join(', ') ?: '—'];
        } else {
            $columns[] = ['key' => 'landing', 'label' => __('Landing page'), 'sort' => true, 'render' => fn ($p) => $p->landing ?: '—'];
        }
        $columns[] = ['key' => 'author', 'label' => __('Author'), 'render' => fn ($p) => $p->author?->name ?? '—'];
        $columns[] = ['key' => 'published_at', 'label' => __('Published'), 'sort' => true, 'render' => fn ($p) => cms_date($p->published_at) ?: '—'];
        $columns[] = ['key' => 'updated_at', 'label' => __('Updated'), 'sort' => true, 'render' => fn ($p) => cms_date($p->updated_at)];

        $filters = ['status' => ['label' => __('Status'), 'options' => ['published' => __('Published'), 'draft' => __('Draft')]]];
        if ($isArticle) {
            $filters['category'] = [
                'label' => __('Category'),
                'options' => Term::where('taxonomy', 'category')->orderBy('name')->pluck('name', 'id')->all(),
                'apply' => fn ($q, $value) => $q->whereHas('terms', fn ($t) => $t->where('terms.id', $value)),
            ];
        }
        if ($this->canManageAll($type)) {
            $filters['author'] = ['label' => __('Author'), 'column' => 'user_id', 'options' => User::orderBy('name')->pluck('name', 'id')->all()];
        }

        return view('admin.table', [
            'title' => $isArticle ? __('Articles') : __('Pages'),
            'actions' => [['label' => $isArticle ? __('New article') : __('New page'), 'url' => route("$base.create")]],
            'table' => DataTable::make([
                'id' => $type,
                'source' => $query,
                'columns' => $columns,
                'search' => ['title', 'slug'],
                'filters' => $filters,
                'default_sort' => ['updated_at', 'desc'],
                'bulk_url' => route("$base.bulk"),
                'bulk' => [
                    'publish' => ['label' => __('Publish')],
                    'draft' => ['label' => __('Move to draft')],
                    'delete' => ['label' => __('Delete'), 'danger' => true],
                ],
                'row_actions' => fn ($p) => [
                    ['label' => __('Edit'), 'icon' => 'pencil', 'url' => route("$base.edit", $p)],
                    ['label' => __('Translate'), 'icon' => 'languages', 'url' => route("$base.translation", $p)],
                    ['label' => __('View'), 'icon' => 'external', 'url' => $p->url(), 'blank' => true],
                    ['label' => __('Delete'), 'icon' => 'trash', 'post' => route("$base.bulk"), 'fields' => ['ids[]' => [$p->id], 'action' => 'delete'], 'danger' => true, 'confirm' => __('Delete ":title"? This cannot be undone.', ['title' => $p->title])],
                ],
                'empty' => __('Nothing here yet. Create the first one.'),
            ]),
        ]);
    }

    private function statusBadge(Post $post): HtmlString
    {
        if ($post->status === 'draft') {
            return new HtmlString('<span class="badge badge-muted">'.e(__('Draft')).'</span>');
        }
        if ($post->published_at?->isFuture()) {
            return new HtmlString('<span class="badge badge-warning">'.e(__('Scheduled')).'</span>');
        }

        return new HtmlString('<span class="badge badge-success">'.e(__('Published')).'</span>');
    }

    private function translationBadge(Post $post): HtmlString
    {
        return new HtmlString(match ($post->translations->firstWhere('locale', Locales::second())?->status) {
            'published' => '<span class="badge badge-success">'.e(__('Published')).'</span>',
            'draft' => '<span class="badge badge-muted">'.e(__('Draft')).'</span>',
            default => '<span class="muted">—</span>',
        });
    }

    public function create(Request $request)
    {
        return $this->form($this->type($request), new Post(['status' => 'draft', 'allow_comments' => true]));
    }

    public function edit(Request $request, Post $post)
    {
        $this->guard($request, $post);

        return $this->form($post->type, $post);
    }

    private function form(string $type, Post $post)
    {
        $isArticle = $type === 'article';

        return view('admin.post-form', [
            'post' => $post,
            'type' => $type,
            'base' => $this->base($type),
            'title' => $post->exists ? __('Edit: :title', ['title' => $post->title]) : ($isArticle ? __('New article') : __('New page')),
            'categories' => Term::where('taxonomy', 'category')->orderBy('name')->get(),
            'selectedCategories' => $post->exists ? $post->categories()->pluck('terms.id')->all() : [],
            'tags' => $post->exists ? $post->tags()->pluck('name')->join(', ') : '',
            'landings' => Landing::options(),
        ]);
    }

    public function store(Request $request)
    {
        $post = new Post(['type' => $this->type($request), 'user_id' => auth()->id()]);

        return $this->persist($request, $post, 'created');
    }

    public function update(Request $request, Post $post)
    {
        $this->guard($request, $post);

        return $this->persist($request, $post, 'updated');
    }

    private function persist(Request $request, Post $post, string $verb)
    {
        $type = $post->type;
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:190',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
            'featured_image' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'focus_keyword' => 'nullable|string|max:190',
            'canonical_url' => 'nullable|url:http,https|max:255',
            'og_image' => 'nullable|string|max:255',
            'landing' => ['nullable', 'string', Rule::in(array_keys(Landing::options()))],
            'menu_order' => 'nullable|integer',
            'categories' => 'array',
            'categories.*' => 'integer',
            'tags' => 'nullable|string|max:1000',
        ]);

        $slug = $this->uniqueSlug($type, ($data['slug'] ?? null) ?: $data['title'], $post->id);
        if ($type === 'page' && in_array($slug, config('cms.reserved_slugs'), true)) {
            return back()->withInput()->withErrors(['slug' => __('The slug ":slug" is reserved by the system.', ['slug' => $slug])]);
        }

        $publishedAt = ! empty($data['published_at'])
            ? Carbon::parse($data['published_at'], setting('timezone', 'Asia/Jakarta'))->utc()
            : ($data['status'] === 'published' ? now() : null);

        $post->fill([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => HtmlSanitizer::clean($data['content'] ?? ''),
            'excerpt' => $data['excerpt'] ?? null,
            'status' => $data['status'],
            'published_at' => $publishedAt,
            'featured_image' => $data['featured_image'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'focus_keyword' => $data['focus_keyword'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'og_image' => $data['og_image'] ?? null,
            'noindex' => $request->boolean('noindex'),
            'allow_comments' => $request->boolean('allow_comments'),
            'show_in_menu' => $request->boolean('show_in_menu'),
            'menu_order' => (int) ($data['menu_order'] ?? 0),
            'landing' => $type === 'page' ? ($data['landing'] ?? null) : null,
        ])->save();

        if ($type === 'article') {
            $this->syncTerms($post, $data['categories'] ?? [], (string) ($data['tags'] ?? ''));
        }

        do_action('post.saved', $post);
        activity("$type.$verb", $post->title);

        return redirect()->route($this->base($type).'.edit', $post)->with('success', __('Saved.'));
    }

    private function syncTerms(Post $post, array $categoryIds, string $tagText): void
    {
        $categories = Term::where('taxonomy', 'category')->whereIn('id', $categoryIds)->pluck('id');
        $tags = collect(explode(',', $tagText))->map(fn ($t) => trim($t))->filter()->unique()->map(function ($name) {
            $slug = Str::slug($name) ?: Str::random(8);

            return Term::firstOrCreate(['taxonomy' => 'tag', 'slug' => $slug], ['name' => $name])->id;
        });
        $post->terms()->sync($categories->merge($tags)->all());
    }

    private function uniqueSlug(string $type, string $source, ?int $ignoreId): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        for ($i = 2; Post::where('type', $type)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function bulk(Request $request)
    {
        $type = $this->type($request);
        $request->validate(['action' => 'required|in:publish,draft,delete']);
        $query = Post::where('type', $type)->whereIn('id', $this->ids($request))
            ->when(! $this->canManageAll($type), fn ($q) => $q->where('user_id', auth()->id()));

        $count = 0;
        foreach ($query->get() as $post) {
            match ($request->input('action')) {
                'publish' => $post->update(['status' => 'published', 'published_at' => $post->published_at ?? now()]),
                'draft' => $post->update(['status' => 'draft']),
                'delete' => $post->delete(),
            };
            $count++;
        }
        activity("$type.".$request->input('action'), (string) $count);

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }

    /** The route type must match the record, and authors may only touch their own articles. */
    private function guard(Request $request, Post $post): void
    {
        abort_unless($post->type === $this->type($request), 404);
        abort_unless($this->canManageAll($post->type) || $post->user_id === auth()->id(), 403);
    }
}
