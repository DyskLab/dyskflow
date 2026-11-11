<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\AdminMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Answers the search box in the admin header. An empty query lists the menus. */
class SearchController extends Controller
{
    private const LIMIT = 6;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim(Str::limit(is_string($request->query('q')) ? $request->query('q') : '', 100, ''));
        $user = $request->user();

        $menus = collect(AdminMenu::items())
            ->map(fn ($item) => ['label' => $item['label'], 'url' => $item['url'], 'keywords' => implode(' ', $item['keywords'] ?? [])])
            ->push(['label' => __('Settings'), 'url' => route('admin.settings.index')])
            ->concat(collect(AdminMenu::settingsCards())->map(fn ($card) => ['label' => $card['title'], 'url' => $card['url'], 'hint' => __('Settings')]))
            // Keywords find a screen by an older or other name, such as "Theme" for the Explorer.
            ->when($term !== '', fn ($items) => $items->filter(fn ($item) => Str::contains($item['label'].' '.($item['keywords'] ?? ''), $term, true)))
            ->map(fn ($item) => array_diff_key($item, ['keywords' => 1]))
            ->values();

        $groups = [['label' => __('Menu'), 'items' => $menus->all()]];

        if (mb_strlen($term) >= 2) {
            if ($user->can('articles.write')) {
                $groups[] = ['label' => __('Articles'), 'items' => $this->posts('article', 'admin.articles.edit', $term, $user->can('articles.manage') ? null : $user->id)];
            }
            if ($user->can('pages.manage')) {
                $groups[] = ['label' => __('Pages'), 'items' => $this->posts('page', 'admin.pages.edit', $term, null)];
            }
        }

        return response()->json(['groups' => array_values(array_filter($groups, fn ($group) => $group['items'] !== []))]);
    }

    private function posts(string $type, string $route, string $term, ?int $onlyAuthor): array
    {
        return Post::where('type', $type)
            ->where('title', 'like', '%'.addcslashes($term, '%_\\').'%')
            ->when($onlyAuthor, fn ($query) => $query->where('user_id', $onlyAuthor))
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get(['id', 'title'])
            ->map(fn ($post) => ['label' => $post->title, 'url' => route($route, $post)])
            ->all();
    }
}
