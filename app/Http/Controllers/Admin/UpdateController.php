<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Notifier;
use App\Support\UpdateService;
use Illuminate\Http\Request;
use Throwable;

class UpdateController extends Controller
{
    public function index(Request $request)
    {
        $result = null;
        $error = null;
        if ($request->boolean('check')) {
            try {
                $result = UpdateService::check();
                Notifier::syncUpdate($result);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.update', [
            'title' => __('Update'),
            'repo' => setting('update_repo', config('cms.repo')),
            'result' => $result,
            'error' => $error,
        ]);
    }

    public function saveRepo(Request $request)
    {
        $data = $request->validate(['update_repo' => ['nullable', 'regex:#^[\w.-]+/[\w.-]+$#']]);
        Setting::write(['update_repo' => $data['update_repo'] ?? '']);
        activity('settings.updated', 'update_repo');

        return back()->with('success', __('Saved.'));
    }

    public function run()
    {
        try {
            $version = UpdateService::run();
            activity('cms.update_downloaded', $version);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('Update failed: :message', ['message' => $e->getMessage()]));
        }

        // Migrations run on a fresh request, which loads the new code and dependencies.
        return redirect()->route('admin.settings.update.finish');
    }

    public function finish()
    {
        try {
            UpdateService::finish();
            Notifier::clearUpdates();
            activity('cms.updated', config('cms.version'));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.settings.update.index')->with('error', __('Update failed: :message', ['message' => $e->getMessage()]));
        }

        return redirect()->route('admin.settings.update.index')->with('success', __('Updated to version :version.', ['version' => config('cms.version')]));
    }
}
