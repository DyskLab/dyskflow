<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\DataTable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $bulk = route('admin.roles.bulk');

        return view('admin.table', [
            'title' => __('Roles'),
            'actions' => [['label' => __('New role'), 'sheet' => route('admin.roles.create')]],
            'table' => DataTable::make([
                'id' => 'roles',
                'source' => Role::query()->withCount('users'),
                'columns' => [
                    ['key' => 'name', 'label' => __('Name'), 'sort' => true],
                    ['key' => 'capabilities', 'label' => __('Permissions'), 'render' => fn ($r) => in_array('*', $r->capabilities, true) ? __('All permissions') : count($r->capabilities).' '.__('permissions')],
                    ['key' => 'users_count', 'label' => __('Users'), 'sort' => true],
                ],
                'search' => ['name', 'slug'],
                'filters' => ['system' => ['label' => __('Type'), 'column' => 'is_system', 'options' => ['1' => __('System'), '0' => __('Custom')]]],
                'default_sort' => ['name', 'asc'],
                'bulk_url' => $bulk,
                'bulk' => ['delete' => ['label' => __('Delete'), 'danger' => true]],
                'row_actions' => fn ($r) => $r->is_system ? [] : [
                    ['label' => __('Edit'), 'icon' => 'pencil', 'sheet' => route('admin.roles.edit', $r->id)],
                    ['label' => __('Delete'), 'icon' => 'trash', 'post' => $bulk, 'fields' => ['ids[]' => [$r->id]], 'danger' => true, 'confirm' => __('Delete ":title"? This cannot be undone.', ['title' => $r->name])],
                ],
            ]),
        ]);
    }

    public function form(?int $id = null)
    {
        $role = $id ? Role::findOrFail($id) : new Role(['capabilities' => []]);
        abort_if($role->is_system, 403);

        return view('admin.partials.sheet-form', [
            'title' => $id ? __('Edit: :title', ['title' => $role->name]) : __('New role'),
            'action' => $id ? route('admin.roles.update', $id) : route('admin.roles.store'),
            'values' => ['name' => $role->name, 'capabilities' => $role->capabilities],
            'fields' => [
                ['name' => 'name', 'label' => __('Name'), 'required' => true],
                ['name' => 'capabilities', 'label' => __('Permissions'), 'type' => 'checkboxes', 'groups' => $this->capabilityGroups()],
            ],
        ]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $role = $id ? Role::findOrFail($id) : new Role;
        abort_if($role->is_system, 403);
        $data = $request->validate(['name' => 'required|string|max:190', 'capabilities' => 'nullable|array']);

        $capabilities = array_values(array_intersect((array) ($data['capabilities'] ?? []), array_keys($this->capabilityOptions())));
        $role->fill([
            'name' => $data['name'],
            'slug' => $role->slug ?: $this->uniqueSlug($data['name']),
            'capabilities' => $capabilities,
        ])->save();
        activity('role.'.($id ? 'updated' : 'created'), $role->name);

        return back()->with('success', __('Saved.'));
    }

    public function bulk(Request $request)
    {
        $count = 0;
        foreach (Role::withCount('users')->whereIn('id', $this->ids($request))->get() as $role) {
            if ($role->is_system || $role->users_count > 0) {
                continue;
            }
            $role->delete();
            activity('role.deleted', $role->name);
            $count++;
        }

        return back()->with('success', __('Done: :count item(s). Roles still in use were kept.', ['count' => $count]));
    }

    /** The permissions in groups, with what each allows, for the role form. */
    private function capabilityGroups(): array
    {
        $held = $this->capabilityOptions();
        $about = [
            'articles.write' => __('Create articles and edit their own. Publishing others\' articles needs the next one.'),
            'articles.manage' => __('Edit, publish and delete every article.'),
            'pages.manage' => __('Pages, builder pages and saved blocks.'),
            'terms.manage' => __('Create, rename and delete categories and tags.'),
            'comments.manage' => __('Approve, mark as spam and delete comments.'),
            'forms.manage' => __('Read, export and delete messages sent with forms. They can hold personal data.'),
            'media.manage' => __('Upload, edit and delete images and files.'),
            'appearance.manage' => __('Templates, blocks and the Explorer: theme, landing pages and custom CSS/JS.'),
            'code.manage' => __('PHP, Blade and HTML widgets: the same access as the server itself. Only for people you trust.'),
            'settings.manage' => __('Site settings, SEO, email and the homepage.'),
            'users.manage' => __('Add users, change their role and roles themselves.'),
            'logs.view' => __('See who did what and when.'),
            'backups.manage' => __('Create, download and restore backups.'),
            'tools.manage' => __('Import and export content.'),
            'updates.manage' => __('Install new versions of DyskFlow.'),
            'plugins.manage' => __('Turn plugins on and off and change their settings.'),
        ];
        $groups = [
            __('Content') => ['articles.write', 'articles.manage', 'pages.manage', 'terms.manage', 'comments.manage', 'forms.manage', 'media.manage'],
            __('Design') => ['appearance.manage', 'code.manage'],
            __('System') => ['settings.manage', 'users.manage', 'logs.view', 'backups.manage', 'tools.manage', 'updates.manage', 'plugins.manage'],
        ];

        return collect($groups)->map(fn (array $keys, string $label) => [
            'label' => $label,
            'options' => collect($keys)->filter(fn ($key) => isset($held[$key]))->mapWithKeys(fn ($key) => [$key => [
                'label' => $held[$key], 'description' => $about[$key] ?? null, 'danger' => in_array($key, ['code.manage', 'users.manage'], true),
            ]])->all(),
        ])->filter(fn ($group) => $group['options'])->values()->all();
    }

    /** Lists only what the current user holds, so a role cannot grant more than its creator has. */
    private function capabilityOptions(): array
    {
        $labels = [
            'articles.write' => __('Write own articles'), 'articles.manage' => __('Manage all articles'),
            'pages.manage' => __('Manage pages'), 'terms.manage' => __('Manage categories and tags'),
            'comments.manage' => __('Moderate comments'), 'media.manage' => __('Manage media'),
            'settings.manage' => __('Change settings'), 'users.manage' => __('Manage users and roles'),
            'logs.view' => __('View activity log'), 'backups.manage' => __('Manage backups'),
            'tools.manage' => __('Import and export'), 'updates.manage' => __('Run updates'), 'plugins.manage' => __('Manage plugins'),
            'appearance.manage' => __('Edit design and files'), 'code.manage' => __('Edit code (PHP and Blade)'),
        ];

        return array_filter($labels, fn ($capability) => auth()->user()->hasCapability($capability), ARRAY_FILTER_USE_KEY);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(6));
        $slug = $base;
        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
