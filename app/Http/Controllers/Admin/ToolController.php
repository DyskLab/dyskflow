<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Setting;
use App\Models\Term;
use App\Models\TermTranslation;
use App\Support\HtmlSanitizer;
use App\Support\Locales;
use App\Support\SettingsSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Import and export of content as portable JSON. Records match by slug. */
class ToolController extends Controller
{
    private const POST_FIELDS = ['type', 'title', 'slug', 'excerpt', 'content', 'status', 'featured_image', 'meta_title', 'meta_description', 'focus_keyword', 'canonical_url', 'og_image', 'noindex', 'allow_comments', 'show_in_menu', 'menu_order', 'published_at'];

    public function index()
    {
        return view('admin.tools', ['title' => __('Import & Export')]);
    }

    public function export(Request $request)
    {
        $include = (array) $request->input('include', []);
        $data = ['generator' => 'template-cms', 'version' => config('cms.version'), 'exported_at' => now()->toIso8601String()];

        if (array_intersect(['articles', 'pages'], $include)) {
            $types = array_values(array_intersect(['article' => 'articles', 'page' => 'pages'], $include));
            $types = array_map(fn ($t) => rtrim($t, 's'), $types);
            $data['terms'] = Term::with('translations')->get()->map(fn ($term) => $term->only(['taxonomy', 'name', 'slug', 'description', 'meta_title', 'meta_description']) + [
                'translations' => $term->translations->map(fn ($t) => $t->only(['locale', ...Term::TRANSLATED]))->all(),
            ])->all();
            $data['posts'] = Post::whereIn('type', $types)->with(['terms:id,taxonomy,slug', 'translations'])->get()->map(function ($post) {
                return collect($post->only(self::POST_FIELDS))->merge([
                    'published_at' => $post->published_at?->toIso8601String(),
                    'terms' => $post->terms->map(fn ($t) => ['taxonomy' => $t->taxonomy, 'slug' => $t->slug])->all(),
                    'translations' => $post->translations->map(fn ($t) => $t->only(['locale', 'status', ...Post::TRANSLATED]))->all(),
                ])->all();
            })->all();
        }
        if (in_array('comments', $include, true)) {
            $data['comments'] = Comment::with('post:id,type,slug')->get()->filter(fn ($c) => $c->post)->map(fn ($c) => [
                'post_type' => $c->post->type, 'post_slug' => $c->post->slug,
                'author_name' => $c->author_name, 'author_email' => $c->author_email, 'author_url' => $c->author_url,
                'content' => $c->content, 'status' => $c->status, 'created_at' => $c->created_at?->toIso8601String(),
            ])->values()->all();
        }
        if (in_array('settings', $include, true)) {
            $data['settings'] = Setting::whereIn('key', $this->portableSettings())->pluck('value', 'key')->all();
        }

        activity('content.exported', implode(', ', $include));

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'export-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|max:51200']);
        $data = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);
        if (! is_array($data) || ($data['generator'] ?? '') !== 'template-cms') {
            return back()->with('error', __('This is not a valid export file.'));
        }

        try {
            $counts = DB::transaction(fn () => $this->runImport($data));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('Import failed: :message', ['message' => $e->getMessage()]));
        }
        activity('content.imported', json_encode($counts));

        return back()->with('success', __('Imported: :posts posts, :terms terms, :comments comments, :settings settings.', $counts));
    }

    private function runImport(array $data): array
    {
        $counts = ['posts' => 0, 'terms' => 0, 'comments' => 0, 'settings' => 0];

        foreach ($data['terms'] ?? [] as $term) {
            if (! in_array($term['taxonomy'] ?? '', ['category', 'tag'], true) || empty($term['name'])) {
                continue;
            }
            $slug = Str::slug($term['slug'] ?? $term['name']);
            $record = Term::updateOrCreate(['taxonomy' => $term['taxonomy'], 'slug' => $slug], [
                'name' => $term['name'], 'description' => $term['description'] ?? null,
                'meta_title' => $term['meta_title'] ?? null, 'meta_description' => $term['meta_description'] ?? null,
            ]);
            foreach ($term['translations'] ?? [] as $item) {
                if (! isset(Locales::ALL[$item['locale'] ?? '']) || empty($item['name'])) {
                    continue;
                }
                $record->translations()->updateOrCreate(['locale' => $item['locale']], [
                    'taxonomy' => $record->taxonomy, 'name' => $item['name'],
                    'slug' => TermTranslation::uniqueSlug($record->taxonomy, $item['locale'], $item['slug'] ?? $item['name'], $record->id),
                    'description' => $item['description'] ?? null,
                    'meta_title' => $item['meta_title'] ?? null, 'meta_description' => $item['meta_description'] ?? null,
                ]);
            }
            $counts['terms']++;
        }

        foreach ($data['posts'] ?? [] as $item) {
            if (! in_array($item['type'] ?? '', ['article', 'page'], true) || empty($item['title']) || empty($item['slug'])) {
                continue;
            }
            $values = array_intersect_key($item, array_flip(self::POST_FIELDS));
            $values['slug'] = Str::slug($item['slug']);
            $values['content'] = HtmlSanitizer::clean($item['content'] ?? '');
            $values['status'] = ($item['status'] ?? '') === 'published' ? 'published' : 'draft';
            $values['published_at'] = ! empty($item['published_at']) ? \Illuminate\Support\Carbon::parse($item['published_at'])->utc() : null;
            // Trashed posts still hold their slug. Importing one brings it back.
            $post = Post::withTrashed()->firstOrNew(['type' => $item['type'], 'slug' => $values['slug']]);
            $post->fill($values);
            $post->deleted_at = null;
            $post->user_id ??= auth()->id();
            $post->save();

            $termIds = collect($item['terms'] ?? [])->map(fn ($t) => Term::where('taxonomy', $t['taxonomy'] ?? '')->where('slug', $t['slug'] ?? '')->value('id'))->filter();
            $post->terms()->sync($termIds->all());
            foreach ($item['translations'] ?? [] as $translation) {
                if (! isset(Locales::ALL[$translation['locale'] ?? '']) || empty($translation['title'])) {
                    continue;
                }
                $post->translations()->updateOrCreate(['locale' => $translation['locale']], [
                    'type' => $post->type, 'title' => $translation['title'],
                    'slug' => PostTranslation::uniqueSlug($post->type, $translation['locale'], $translation['slug'] ?? $translation['title'], $post->id),
                    'status' => ($translation['status'] ?? '') === 'published' ? 'published' : 'draft',
                    'content' => HtmlSanitizer::clean($translation['content'] ?? ''),
                    'excerpt' => $translation['excerpt'] ?? null,
                    'meta_title' => $translation['meta_title'] ?? null, 'meta_description' => $translation['meta_description'] ?? null,
                    'focus_keyword' => $translation['focus_keyword'] ?? null, 'canonical_url' => $translation['canonical_url'] ?? null,
                    'noindex' => ! empty($translation['noindex']),
                ]);
            }
            $counts['posts']++;
        }

        foreach ($data['comments'] ?? [] as $item) {
            $post = Post::where('type', $item['post_type'] ?? '')->where('slug', $item['post_slug'] ?? '')->first();
            if (! $post || empty($item['author_name']) || empty($item['content'])) {
                continue;
            }
            $status = in_array($item['status'] ?? '', ['pending', 'approved', 'spam'], true) ? $item['status'] : 'pending';
            Comment::withTrashed()->firstOrCreate(
                ['post_id' => $post->id, 'author_name' => $item['author_name'], 'content' => $item['content']],
                ['author_email' => $item['author_email'] ?? null, 'author_url' => $item['author_url'] ?? null, 'status' => $status],
            );
            $counts['comments']++;
        }

        $settings = array_intersect_key((array) ($data['settings'] ?? []), array_flip($this->portableSettings()));
        Setting::write($settings);
        $counts['settings'] = count($settings);

        return $counts;
    }

    /** Excludes secrets and values that belong to a single installation. */
    private function portableSettings(): array
    {
        return collect(SettingsSchema::sections())
            ->flatMap(fn ($section) => collect($section['fields'])->pluck('name'))
            ->reject(fn ($name) => in_array($name, ['mail_password', 'site_url', 'update_repo', 'home_page_id'], true))
            ->values()->all();
    }
}
