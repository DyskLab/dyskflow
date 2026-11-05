<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Models\TermTranslation;
use App\Support\DataTable;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Categories and tags share this controller. The route supplies the taxonomy. */
class TermController extends Controller
{
    private function taxonomy(Request $request): string
    {
        return $request->route('taxonomy');
    }

    private function base(string $taxonomy): string
    {
        return $taxonomy === 'category' ? 'admin.categories' : 'admin.tags';
    }

    public function index(Request $request)
    {
        $taxonomy = $this->taxonomy($request);
        $base = $this->base($taxonomy);
        $isCategory = $taxonomy === 'category';

        return view('admin.table', [
            'title' => $isCategory ? __('Categories') : __('Tags'),
            'actions' => [['label' => $isCategory ? __('New category') : __('New tag'), 'sheet' => route("$base.create")]],
            'table' => DataTable::make([
                'id' => $taxonomy,
                'source' => Term::query()->where('taxonomy', $taxonomy)->withCount('posts'),
                'columns' => [
                    ['key' => 'name', 'label' => __('Name'), 'sort' => true],
                    ['key' => 'slug', 'label' => __('Slug'), 'sort' => true],
                    ['key' => 'description', 'label' => __('Description'), 'render' => fn ($t) => Str::limit((string) $t->description, 80) ?: '—'],
                    ['key' => 'posts_count', 'label' => __('Articles'), 'sort' => true],
                ],
                'search' => ['name', 'slug'],
                'filters' => ['used' => [
                    'label' => __('Usage'),
                    'options' => ['yes' => __('Used'), 'no' => __('Unused')],
                    'apply' => fn ($q, $value) => $value === 'yes' ? $q->has('posts') : $q->doesntHave('posts'),
                ]],
                'default_sort' => ['name', 'asc'],
                'bulk_url' => route("$base.bulk"),
                'bulk' => ['delete' => ['label' => __('Delete'), 'danger' => true]],
                'row_actions' => fn ($t) => [
                    ['label' => __('Edit'), 'icon' => 'pencil', 'sheet' => route("$base.edit", $t->id)],
                    ['label' => __('View'), 'icon' => 'external', 'url' => $t->url(), 'blank' => true],
                    ['label' => __('Delete'), 'icon' => 'trash', 'post' => route("$base.bulk"), 'fields' => ['ids[]' => [$t->id]], 'danger' => true, 'confirm' => __('Delete ":title"? This cannot be undone.', ['title' => $t->name])],
                ],
            ]),
        ]);
    }

    public function form(Request $request)
    {
        // Read from the route: on create, the only route value is the taxonomy default.
        $id = $request->route('id');
        $taxonomy = $this->taxonomy($request);
        $base = $this->base($taxonomy);
        $language = Locales::name(Locales::second());
        $term = $id ? Term::where('taxonomy', $taxonomy)->findOrFail($id) : new Term;

        return view('admin.partials.sheet-form', [
            'title' => $id ? __('Edit: :title', ['title' => $term->name]) : ($taxonomy === 'category' ? __('New category') : __('New tag')),
            'action' => $id ? route("$base.update", $id) : route("$base.store"),
            'values' => $term->toArray() + $this->translatedValues($term),
            'fields' => [
                ['name' => 'name', 'label' => __('Name'), 'required' => true],
                ['name' => 'slug', 'label' => __('Slug'), 'slug_from' => 'name'],
                ['name' => 'description', 'label' => __('Description'), 'type' => 'textarea'],
                ['name' => 'meta_title', 'label' => __('Meta title')],
                ['name' => 'meta_description', 'label' => __('Meta description'), 'type' => 'textarea'],
                ['name' => 'translated_name', 'label' => __('Name in :language', ['language' => $language]), 'help' => __('Leave empty to remove the translation.')],
                ['name' => 'translated_slug', 'label' => __('Slug in :language', ['language' => $language]), 'slug_from' => 'translated_name'],
                ['name' => 'translated_description', 'label' => __('Description in :language', ['language' => $language]), 'type' => 'textarea'],
                ['name' => 'translated_meta_title', 'label' => __('Meta title in :language', ['language' => $language])],
                ['name' => 'translated_meta_description', 'label' => __('Meta description in :language', ['language' => $language]), 'type' => 'textarea'],
            ],
        ]);
    }

    private function translatedValues(Term $term): array
    {
        $translation = $term->exists ? $term->translations()->where('locale', Locales::second())->first() : null;

        return collect(Term::TRANSLATED)->mapWithKeys(fn ($field) => ["translated_$field" => $translation?->$field])->all();
    }

    public function save(Request $request)
    {
        $id = $request->route('id');
        $taxonomy = $this->taxonomy($request);
        $term = $id ? Term::where('taxonomy', $taxonomy)->findOrFail($id) : new Term(['taxonomy' => $taxonomy]);
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'slug' => 'nullable|string|max:190',
            'description' => 'nullable|string|max:2000',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);
        $translated = $request->validate([
            'translated_name' => 'nullable|string|max:190',
            'translated_slug' => 'nullable|string|max:190',
            'translated_description' => 'nullable|string|max:2000',
            'translated_meta_title' => 'nullable|string|max:255',
            'translated_meta_description' => 'nullable|string|max:500',
        ]);

        $base = Str::slug(($data['slug'] ?? null) ?: $data['name']) ?: Str::lower(Str::random(8));
        $slug = $base;
        for ($i = 2; Term::where('taxonomy', $taxonomy)->where('slug', $slug)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        $term->fill(['slug' => $slug] + $data)->save();
        $this->saveTranslation($term, $translated);
        activity("$taxonomy.".($id ? 'updated' : 'created'), $term->name);

        return back()->with('success', __('Saved.'));
    }

    private function saveTranslation(Term $term, array $data): void
    {
        $locale = Locales::second();
        if (! ($data['translated_name'] ?? null)) {
            $term->translations()->where('locale', $locale)->delete();

            return;
        }

        $term->translations()->updateOrCreate(['locale' => $locale], [
            'taxonomy' => $term->taxonomy,
            'name' => $data['translated_name'],
            'slug' => TermTranslation::uniqueSlug($term->taxonomy, $locale, ($data['translated_slug'] ?? null) ?: $data['translated_name'], $term->id),
            'description' => $data['translated_description'] ?? null,
            'meta_title' => $data['translated_meta_title'] ?? null,
            'meta_description' => $data['translated_meta_description'] ?? null,
        ]);
    }

    public function bulk(Request $request)
    {
        $taxonomy = $this->taxonomy($request);
        $count = Term::where('taxonomy', $taxonomy)->whereIn('id', $this->ids($request))->delete();
        activity("$taxonomy.delete", (string) $count);

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }
}
