<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Support\HtmlSanitizer;
use App\Support\Locales;
use Illuminate\Http\Request;

/** The second-language version of an article or page. The original keeps its own screen. */
class PostTranslationController extends Controller
{
    public function edit(Request $request, Post $post)
    {
        $this->guard($request, $post);
        $locale = Locales::second();
        $translation = $post->translations()->firstOrNew(['locale' => $locale], ['status' => 'draft']);

        return view('admin.post-translation', [
            'original' => $post,
            'post' => $translation,
            'locale' => $locale,
            'type' => $post->type,
            'base' => $this->base($post),
            'viewUrl' => $translation->exists ? (clone $post)->localize($locale)->url() : null,
            'title' => __('Translate: :title', ['title' => $post->title]),
        ]);
    }

    public function save(Request $request, Post $post)
    {
        $this->guard($request, $post);
        $locale = Locales::second();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:190',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,published',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'focus_keyword' => 'nullable|string|max:190',
            'canonical_url' => 'nullable|url:http,https|max:255',
        ]);

        $slug = PostTranslation::uniqueSlug($post->type, $locale, ($data['slug'] ?? null) ?: $data['title'], $post->id);
        if ($post->type === 'page' && in_array($slug, config('cms.reserved_slugs'), true)) {
            return back()->withInput()->withErrors(['slug' => __('The slug ":slug" is reserved by the system.', ['slug' => $slug])]);
        }

        $post->translations()->updateOrCreate(['locale' => $locale], [
            'type' => $post->type,
            'title' => $data['title'],
            'slug' => $slug,
            'status' => $data['status'],
            'content' => HtmlSanitizer::clean($data['content'] ?? ''),
            'excerpt' => $data['excerpt'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'focus_keyword' => $data['focus_keyword'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'noindex' => $request->boolean('noindex'),
        ]);

        activity("{$post->type}.translated", $post->title);

        return redirect()->route($this->base($post).'.translation', $post)->with('success', __('Saved.'));
    }

    public function destroy(Request $request, Post $post)
    {
        $this->guard($request, $post);
        $post->translations()->where('locale', Locales::second())->delete();
        activity("{$post->type}.translation_deleted", $post->title);

        return redirect()->route($this->base($post).'.edit', $post)->with('success', __('Translation deleted.'));
    }

    private function base(Post $post): string
    {
        return $post->type === 'article' ? 'admin.articles' : 'admin.pages';
    }

    /** The same rule as editing the original: authors may only touch their own articles. */
    private function guard(Request $request, Post $post): void
    {
        abort_unless($post->type === $request->route('type'), 404);
        abort_unless($post->type === 'page' || auth()->user()->can('articles.manage') || $post->user_id === auth()->id(), 403);
    }
}
