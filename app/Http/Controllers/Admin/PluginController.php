<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DataTable;
use App\Support\Plugins;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Throwable;

class PluginController extends Controller
{
    public function index()
    {
        $bulk = route('admin.settings.plugins.bulk');

        return view('admin.table', [
            'title' => __('Plugins'),
            'description' => __('Add a plugin by placing its folder inside plugins/.'),
            'table' => DataTable::make([
                'id' => 'plugins',
                'source' => Plugins::all(),
                'row_id' => fn ($p) => $p['slug'],
                'columns' => [
                    ['key' => 'name', 'label' => __('Name'), 'sort' => true, 'render' => fn ($p) => new HtmlString('<div style="font-weight:500">'.e($p['name']).'</div><div class="muted small">'.e($p['description']).'</div>')],
                    ['key' => 'version', 'label' => __('Version'), 'sort' => true],
                    ['key' => 'author', 'label' => __('Author'), 'sort' => true],
                    ['key' => 'status', 'label' => __('Status'), 'sort' => true, 'render' => fn ($p) => new HtmlString($p['active'] ? '<span class="badge badge-success">'.e(__('On')).'</span>' : '<span class="badge badge-muted">'.e(__('Off')).'</span>')],
                ],
                'search' => ['name', 'description', 'author'],
                'filters' => ['status' => ['label' => __('Status'), 'options' => ['active' => __('On'), 'inactive' => __('Off')]]],
                'default_sort' => ['name', 'asc'],
                'bulk_url' => $bulk,
                'bulk' => ['on' => ['label' => __('Turn on')], 'off' => ['label' => __('Turn off')]],
                'row_actions' => fn ($p) => [
                    ['label' => $p['active'] ? __('Turn off') : __('Turn on'), 'icon' => $p['active'] ? 'ban' : 'check', 'post' => $bulk, 'fields' => ['ids[]' => [$p['slug']], 'action' => $p['active'] ? 'off' : 'on']],
                    $p['settings_url'] && $p['active'] ? ['label' => __('Settings'), 'icon' => 'settings', 'url' => url($p['settings_url'])]
                        : ($p['settings'] ? ['label' => __('Settings'), 'icon' => 'settings', 'sheet' => route('admin.settings.plugins.settings', $p['slug'])] : null),
                ],
                'empty' => __('No plugins found. Add a folder inside plugins/.'),
            ]),
        ]);
    }

    public function bulk(Request $request)
    {
        $action = $request->validate(['action' => 'required|in:on,off'])['action'];
        $count = 0;
        try {
            foreach ($this->keys($request) as $slug) {
                if (Plugins::manifest($slug)) {
                    Plugins::setActive($slug, $action === 'on');
                    activity("plugin.$action", $slug);
                    $count++;
                }
            }
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }

    public function settings(string $slug)
    {
        $manifest = Plugins::manifest($slug) ?? abort(404);

        return view('admin.partials.sheet-form', [
            'title' => $manifest['name'] ?? $slug,
            'description' => __('Plugin settings'),
            'action' => route('admin.settings.plugins.settings', $slug),
            'fields' => $manifest['settings'],
            'values' => collect($manifest['settings'])->mapWithKeys(fn ($f) => [$f['name'] => Plugins::setting($slug, $f['name'], $f['default'] ?? null)])->all(),
        ]);
    }

    public function saveSettings(Request $request, string $slug)
    {
        $manifest = Plugins::manifest($slug) ?? abort(404);
        $rules = [];
        foreach ($manifest['settings'] as $field) {
            $rules[$field['name']] = match ($field['type'] ?? 'text') {
                'switch' => ['nullable', 'in:0,1'],
                'number' => ['nullable', 'numeric'],
                'email' => ['nullable', 'email'],
                'url' => ['nullable', 'url:http,https'],
                'select' => ['nullable', Rule::in(array_map('strval', array_keys($field['options'] ?? [])))],
                default => ['nullable', 'string', 'max:10000'],
            };
        }
        Plugins::saveSettings($slug, $request->validate($rules));
        activity('plugin.settings', $slug);

        return back()->with('success', __('Saved.'));
    }
}
