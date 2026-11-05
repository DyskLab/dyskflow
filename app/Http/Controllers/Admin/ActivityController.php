<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\DataTable;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index()
    {
        $actions = ActivityLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all();
        $users = ActivityLog::query()->whereNotNull('user_name')->distinct()->orderBy('user_name')->pluck('user_name', 'user_name')->all();

        return view('admin.table', [
            'title' => __('Activity log'),
            'table' => DataTable::make([
                'id' => 'logs',
                'source' => ActivityLog::query(),
                'columns' => [
                    ['key' => 'created_at', 'label' => __('Time'), 'sort' => true, 'render' => fn ($l) => cms_date($l->created_at)],
                    ['key' => 'user_name', 'label' => __('User'), 'sort' => true, 'render' => fn ($l) => $l->user_name ?: '—'],
                    ['key' => 'action', 'label' => __('Action'), 'sort' => true],
                    ['key' => 'subject', 'label' => __('Subject'), 'render' => fn ($l) => $l->subject ?: '—'],
                    ['key' => 'ip', 'label' => 'IP'],
                ],
                'search' => ['action', 'subject', 'user_name', 'ip'],
                'filters' => [
                    'action' => ['label' => __('Action'), 'options' => $actions],
                    'user' => ['label' => __('User'), 'column' => 'user_name', 'options' => $users],
                ],
                'default_sort' => ['created_at', 'desc'],
                'bulk_url' => route('admin.settings.logs.bulk'),
                'bulk' => ['delete' => ['label' => __('Delete'), 'danger' => true]],
            ]),
        ]);
    }

    public function bulk(Request $request)
    {
        $count = ActivityLog::whereIn('id', $this->ids($request))->delete();

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }
}
