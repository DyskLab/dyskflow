<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FileRevision;
use App\Models\Post;
use App\Support\Builder\Builder;
use App\Support\Builder\Templates;
use App\Support\DataTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/**
 * Design > Templates: Theme Builder templates for the article, the lists, the header and the
 * footer, edited in the builder and active when published. A kind can have several templates,
 * each limited by conditions (a category, a page...); one without conditions is used elsewhere.
 */
class TemplateController extends Controller
{
    public function index()
    {
        $kinds = Templates::kinds();
        $posts = Post::where('type', 'template')->orderBy('id')->get()
            ->filter(fn (Post $p) => isset($kinds[Templates::kindOf($p)]))
            ->sortBy(fn (Post $p) => [array_search(Templates::kindOf($p), array_keys($kinds), true), $p->slug === Templates::kindOf($p) ? 0 : 1, $p->id]);
        $made = $posts->map(fn (Post $p) => Templates::kindOf($p))->unique()->all();

        $rows = $posts->map(fn (Post $p) => [
            'id' => (string) $p->id,
            'kind' => Templates::kindOf($p),
            'post' => $p,
            'label' => $p->title,
            'description' => $kinds[Templates::kindOf($p)]['label'],
            'where' => Templates::describe($p),
            'status' => $p->status === 'published' ? 'active' : 'inactive',
            'changes' => $p->builder_draft !== null,
        ])->values();
        // Kinds without a template stay listed, so it is clear the theme is used there.
        foreach ($kinds as $kind => $info) {
            if (! in_array($kind, $made, true)) {
                $rows->push(['id' => $kind, 'kind' => $kind, 'post' => null, 'label' => $info['label'], 'description' => $info['description'], 'where' => '', 'status' => 'none', 'changes' => false]);
            }
        }
        $statuses = ['active' => __('Active'), 'inactive' => __('Off: the theme is used'), 'none' => __('Not made: the theme is used')];

        return view('admin.table', [
            'title' => __('Templates'),
            'description' => __('Design the article, the article lists, the header and the footer with the builder, like Elementor\'s Theme Builder. A template is used once it is active; without one, the theme\'s own look stays. Conditions limit a template to some categories or pages.'),
            'actions' => [['label' => __('New template'), 'icon' => 'plus', 'sheet' => route('admin.design.templates.create')]],
            'table' => DataTable::make([
                'id' => 'templates',
                'source' => $rows,
                'row_id' => fn ($row) => $row['id'],
                'columns' => [
                    ['key' => 'label', 'label' => __('Template'), 'render' => fn ($row) => new HtmlString('<strong>'.e($row['label']).'</strong><div class="muted small">'.e($row['description']).'</div>')],
                    ['key' => 'where', 'label' => __('Used on'), 'render' => fn ($row) => $row['post'] ? new HtmlString('<span class="small">'.e($row['where']).'</span>') : new HtmlString('<span class="muted">—</span>')],
                    ['key' => 'status', 'label' => __('Status'), 'render' => fn ($row) => new HtmlString(
                        '<span class="badge '.($row['status'] === 'active' ? 'badge-success' : 'badge-muted').'">'.e($statuses[$row['status']]).'</span>'
                        .($row['changes'] ? ' <span class="badge badge-warning">'.e(__('Unpublished changes')).'</span>' : '')
                    )],
                ],
                'search' => ['label', 'description', 'where'],
                'filters' => [
                    'kind' => ['label' => __('Type'), 'options' => array_map(fn ($k) => $k['label'], $kinds)],
                    'status' => ['label' => __('Status'), 'options' => $statuses],
                ],
                'bulk_url' => route('admin.design.templates.index'),
                'row_actions' => fn ($row) => $row['post'] ? [
                    ['label' => __('Edit with the builder'), 'icon' => 'landing', 'url' => route('admin.design.templates.builder.edit', $row['post'])],
                    ['label' => __('Conditions'), 'icon' => 'settings', 'sheet' => route('admin.design.templates.conditions', $row['post'])],
                    $row['status'] === 'active'
                        ? ['label' => __('Turn off'), 'icon' => 'ban', 'post' => route('admin.design.templates.toggle', $row['post']), 'confirm' => __('Turn this template off? The theme\'s own look is used again; the template is kept.')]
                        : ['label' => __('Turn on'), 'icon' => 'check', 'post' => route('admin.design.templates.toggle', $row['post'])],
                    ['label' => __('Revisions'), 'icon' => 'restore', 'url' => route('admin.design.templates.builder.revisions', $row['post'])],
                    ['label' => __('Delete'), 'icon' => 'trash', 'danger' => true, 'post' => route('admin.design.templates.delete', $row['post']), 'confirm' => __('Delete this template and its revisions? The theme\'s own look is used again.')],
                ] : [
                    ['label' => __('Create'), 'icon' => 'plus', 'post' => route('admin.design.templates.store'), 'fields' => ['kind' => $row['kind']]],
                ],
                'empty' => __('No templates.'),
            ]),
        ]);
    }

    public function createForm()
    {
        return view('admin.partials.sheet-form', [
            'title' => __('New template'),
            'description' => __('One more template of a kind can be limited to some categories or pages with Conditions, for example a different header on the homepage.'),
            'action' => route('admin.design.templates.store'),
            'values' => [],
            'fields' => [
                ['name' => 'kind', 'label' => __('Type'), 'type' => 'select', 'options' => array_map(fn ($k) => $k['label'], Templates::kinds()), 'required' => true],
                ['name' => 'title', 'label' => __('Name'), 'maxlength' => 255, 'help' => __('Only shown in the admin. Empty uses the type.')],
            ],
            'submit' => __('Create'),
        ]);
    }

    /**
     * A new template starts as a draft that looks like the theme, so turning it on changes nothing
     * until it is edited. The first of a kind is its main template, used everywhere.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => 'required|string|in:'.implode(',', array_keys(Templates::KINDS)),
            'title' => 'nullable|string|max:255',
        ]);
        $kind = $data['kind'];
        $spec = Templates::starter($kind);
        $post = Post::create([
            'type' => 'template',
            'slug' => Templates::newSlug($kind),
            'title' => ($data['title'] ?? '') !== '' ? $data['title'] : Templates::kinds()[$kind]['label'],
            'content' => '',
            'status' => 'draft',
            'user_id' => auth()->id(),
            'allow_comments' => false,
            'builder' => Builder::compact(Builder::blank($spec['layout'])),
            'builder_draft' => Builder::compact($spec),
        ]);
        activity('template.created', $post->title);

        return redirect()->route('admin.design.templates.builder.edit', $post)->with('success', $post->slug === $kind
            ? __('Template created. Publish it to use it on the site.')
            : __('Template created. Choose where it is used with Conditions in Design > Templates, then publish it.'));
    }

    public function conditionsForm(Post $template)
    {
        $this->template($template);

        return view('admin.partials.sheet-form', [
            'title' => __('Conditions'),
            'description' => __('Choose where this template is used. With nothing ticked it is used everywhere the other templates of its type are not. A template with conditions wins over one without.'),
            'action' => route('admin.design.templates.conditions.save', $template),
            'values' => ['title' => $template->title, 'conditions' => array_values((array) $template->conditions)],
            'fields' => [
                ['name' => 'title', 'label' => __('Name'), 'required' => true, 'maxlength' => 255],
                ['name' => 'conditions', 'label' => __('Used on'), 'type' => 'checkboxes', 'groups' => Templates::conditionGroups(Templates::kindOf($template))],
            ],
            'submit' => __('Save'),
        ]);
    }

    public function saveConditions(Request $request, Post $template): RedirectResponse
    {
        $this->template($template);
        $options = Templates::conditionOptions(Templates::kindOf($template));
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'conditions' => 'array',
            'conditions.*' => 'nullable|string|in:'.implode(',', array_keys($options)),
        ]);
        $conditions = array_values(array_unique(array_filter($data['conditions'] ?? [])));
        $template->forceFill(['title' => $data['title'], 'conditions' => $conditions ?: null])->save();
        activity('template.updated', $template->title);

        // Opened from the list or from the builder's menu: go back to where it was opened.
        return back()->with('success', __('Conditions saved.'));
    }

    public function toggle(Post $template): RedirectResponse
    {
        $this->template($template);
        $on = $template->status !== 'published';
        if ($on && empty($template->builderSpec()['sections'])) {
            return back()->with('error', __('Publish the template in the builder first: it has no published layout yet.'));
        }
        $template->forceFill(['status' => $on ? 'published' : 'draft', 'published_at' => $template->published_at ?? now()])->save();
        activity($on ? 'template.activated' : 'template.deactivated', $template->title);

        return back()->with('success', $on ? __('Template turned on.') : __('Template turned off. The theme\'s own look is used.'));
    }

    public function destroy(Post $template): RedirectResponse
    {
        $this->template($template);
        FileRevision::where('path', BuilderController::revisionPath($template))->delete();
        $template->forceDelete();
        activity('template.deleted', $template->title);

        return redirect()->route('admin.design.templates.index')->with('success', __('Template deleted.'));
    }

    private function template(Post $post): void
    {
        abort_unless($post->type === 'template', 404);
    }
}
