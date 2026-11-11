<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DataTable;
use App\Support\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/** The default theme files, opened from the Explorer's theme/ folder (FileController). */
class DesignController extends Controller
{
    public function theme()
    {
        $canCode = $this->canCode();

        return view('admin.table', [
            'title' => __('Default theme files'),
            'description' => __('Copy a theme file to change it. Your copy is kept in theme/ and survives updates. Reset to go back to the original.'),
            'actions' => [['label' => __('Open theme/ in the Explorer'), 'icon' => 'folder', 'secondary' => true, 'url' => route('admin.design.files.index', ['dir' => 'theme'])]],
            'table' => DataTable::make([
                'id' => 'theme',
                'source' => collect(Theme::files())->map(fn (array $file) => $file + ['status' => $file['overridden'] ? 'overridden' : 'default']),
                'row_id' => fn ($file) => $file['path'],
                'columns' => [
                    ['key' => 'path', 'label' => __('File'), 'sort' => true, 'render' => fn ($file) => new HtmlString('<span class="mono">'.e($file['path']).'</span>')],
                    ['key' => 'kind', 'label' => __('Type'), 'sort' => true, 'render' => fn ($file) => $file['kind'] === 'view' ? __('Template') : __('Style or script')],
                    ['key' => 'status', 'label' => __('Status'), 'sort' => true, 'render' => fn ($file) => new HtmlString($file['overridden']
                        ? '<span class="badge badge-warning">'.e(__('Changed')).'</span>'
                        : '<span class="badge badge-muted">'.e(__('Original')).'</span>')],
                ],
                'search' => ['path'],
                'filters' => [
                    'kind' => ['label' => __('Type'), 'options' => ['view' => __('Template'), 'asset' => __('Style or script')]],
                    'status' => ['label' => __('Status'), 'options' => ['overridden' => __('Changed'), 'default' => __('Original')]],
                ],
                'default_sort' => ['path', 'asc'],
                'bulk_url' => route('admin.design.theme.bulk'),
                'bulk' => ['reset' => ['label' => __('Reset to original'), 'danger' => true, 'confirm' => __('Your changes to the selected files are removed. Each one is kept as a revision.')]],
                'row_actions' => fn ($file) => [
                    $file['overridden'] ? null : (($file['kind'] === 'asset' || $canCode)
                        ? ['label' => __('Copy to theme'), 'icon' => 'plus', 'post' => route('admin.design.theme.override'), 'fields' => ['path' => $file['path']]]
                        : null),
                    $file['overridden'] ? ['label' => __('Reset to original'), 'icon' => 'restore', 'post' => route('admin.design.theme.bulk'), 'fields' => ['action' => 'reset', 'ids[]' => [$file['path']]], 'danger' => true, 'confirm' => __('Your changes to ":title" are removed. They are kept as a revision.', ['title' => $file['path']])] : null,
                ],
                'empty' => __('No theme files found.'),
            ]),
        ]);
    }

    public function overrideFile(Request $request)
    {
        $path = (string) $request->input('path');
        // Templates run as PHP on the server.
        abort_if(str_starts_with($path, 'site/') && ! $this->canCode(), 403);

        $target = Theme::override($path);
        activity('theme.overridden', $path);

        return redirect()->route('admin.design.files.edit', ['path' => $target])->with('success', __('Copied to :path.', ['path' => $target]));
    }

    public function themeBulk(Request $request)
    {
        abort_unless($request->input('action') === 'reset', 422);

        $count = 0;
        foreach ($this->keys($request) as $path) {
            if (in_array($path, Theme::paths(), true) && is_file(Theme::overridePath($path))) {
                Theme::reset($path);
                activity('theme.reset', $path);
                $count++;
            }
        }

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }

    /** PHP and Blade can be edited only with code.manage, and not at all while CMS_CODE_EDITOR is off. */
    private function canCode(): bool
    {
        return (bool) config('cms.code_editor') && (bool) auth()->user()?->can('code.manage');
    }
}
