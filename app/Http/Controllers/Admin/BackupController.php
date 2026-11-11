<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BackupService;
use App\Support\DataTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Throwable;

class BackupController extends Controller
{
    public function index()
    {
        return view('admin.table', [
            'title' => __('Backup'),
            'description' => __('Backups contain the database. Full backups also contain uploaded files and the theme/ and landing/ folders.'),
            'actions' => [
                ['label' => __('Database backup'), 'icon' => 'backup', 'secondary' => true, 'post' => route('admin.settings.backups.store'), 'fields' => ['kind' => 'db']],
                ['label' => __('Full backup'), 'icon' => 'backup', 'post' => route('admin.settings.backups.store'), 'fields' => ['kind' => 'full']],
            ],
            'table' => DataTable::make([
                'id' => 'backups',
                'source' => BackupService::all(),
                'columns' => [
                    ['key' => 'name', 'label' => __('File'), 'sort' => true],
                    ['key' => 'kind', 'label' => __('Type'), 'sort' => true, 'render' => fn ($b) => $b['kind'] === 'full' ? __('Full') : __('Database')],
                    ['key' => 'size', 'label' => __('Size'), 'sort' => true, 'render' => fn ($b) => Number::fileSize($b['size'])],
                    ['key' => 'created', 'label' => __('Created'), 'sort' => true, 'render' => fn ($b) => cms_date('@'.$b['created'])],
                ],
                'search' => ['name'],
                'filters' => ['kind' => ['label' => __('Type'), 'options' => ['full' => __('Full'), 'database' => __('Database')]]],
                'default_sort' => ['created', 'desc'],
                'bulk_url' => route('admin.settings.backups.bulk'),
                'bulk' => ['delete' => ['label' => __('Delete'), 'danger' => true]],
                'row_actions' => fn ($b) => [
                    ['label' => __('Download'), 'icon' => 'download', 'url' => route('admin.settings.backups.download', $b['name'])],
                    ['label' => __('Restore'), 'icon' => 'restore', 'post' => route('admin.settings.backups.restore', $b['name']), 'danger' => true, 'confirm' => __('All current content will be replaced by this backup. A safety backup is made first.')],
                    ['label' => __('Delete'), 'icon' => 'trash', 'post' => route('admin.settings.backups.bulk'), 'fields' => ['ids[]' => [$b['name']]], 'danger' => true, 'confirm' => __('Delete ":title"? This cannot be undone.', ['title' => $b['name']])],
                ],
                'empty' => __('No backups yet.'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $name = BackupService::create($request->input('kind') === 'full');
            activity('backup.created', $name);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Backup created: :name', ['name' => $name]));
    }

    public function download(string $name)
    {
        $path = BackupService::path($name) ?? abort(404);
        activity('backup.downloaded', $name);

        return response()->download($path);
    }

    public function restore(Request $request, string $name)
    {
        $path = BackupService::path($name) ?? abort(404);
        try {
            BackupService::create(false);
            BackupService::restore($path);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('Restore failed: :message', ['message' => $e->getMessage()]));
        }

        // The restored users table may no longer contain the current account.
        if (! Auth::user()?->fresh()) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login');
        }
        activity('backup.restored', $name);

        return back()->with('success', __('Backup restored.'));
    }

    public function bulk(Request $request)
    {
        $count = 0;
        foreach ($this->keys($request) as $name) {
            if ($path = BackupService::path($name)) {
                unlink($path);
                activity('backup.deleted', $name);
                $count++;
            }
        }

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }
}
