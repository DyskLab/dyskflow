<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Support\DataTable;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $types = ['comment' => __('Comments'), 'update' => __('Update'), 'error' => __('Errors'), 'system' => __('System')];

        return view('admin.table', [
            'title' => __('Notifications'),
            'table' => DataTable::make([
                'id' => 'notifications',
                'source' => AdminNotification::visibleTo($user)->withReadState($user),
                'columns' => [
                    ['key' => 'title', 'label' => __('Notification'), 'render' => fn ($n) => $this->message($n)],
                    ['key' => 'type', 'label' => __('Type'), 'sort' => true, 'render' => fn ($n) => new HtmlString('<span class="badge '.($n->type === 'error' ? 'badge-danger' : 'badge-muted').'">'.e($types[$n->type] ?? $types['system']).'</span>')],
                    ['key' => 'updated_at', 'label' => __('Time'), 'sort' => true, 'render' => fn ($n) => cms_date($n->updated_at)],
                ],
                'search' => ['title', 'body'],
                'filters' => [
                    'type' => ['label' => __('Type'), 'options' => $types],
                    'state' => [
                        'label' => __('Status'),
                        'options' => ['unread' => __('Unread'), 'read' => __('Read')],
                        'apply' => fn ($query, $value) => $value === 'unread'
                            ? $query->unreadBy($user)
                            : $query->whereHas('readers', fn ($q) => $q->where('users.id', $user->id)),
                    ],
                ],
                'default_sort' => ['updated_at', 'desc'],
                'bulk_url' => route('admin.notifications.bulk'),
                'bulk' => [
                    'read' => ['label' => __('Mark as read')],
                    'delete' => ['label' => __('Delete'), 'danger' => true],
                ],
                'empty' => __('No notifications yet.'),
            ]),
        ]);
    }

    public function open(Request $request, int $id)
    {
        $notification = AdminNotification::visibleTo($request->user())->findOrFail($id);
        $notification->markReadBy($request->user());

        return $notification->url ? redirect(url($notification->url)) : redirect()->route('admin.notifications.index');
    }

    public function readAll(Request $request)
    {
        $user = $request->user();
        AdminNotification::visibleTo($user)->unreadBy($user)->pluck('id')
            ->each(fn ($id) => AdminNotification::find($id)->markReadBy($user));

        return back();
    }

    public function bulk(Request $request)
    {
        $action = $request->validate(['action' => 'required|in:read,delete'])['action'];
        $rows = AdminNotification::visibleTo($request->user())->whereIn('id', $this->ids($request))->get();
        $rows->each(fn ($row) => $action === 'delete' ? $row->delete() : $row->markReadBy($request->user()));

        return back()->with('success', __('Done: :count item(s).', ['count' => $rows->count()]));
    }

    /** Asked for by the header when a check is due, so a slow GitHub never delays the page itself. */
    public function check(Request $request)
    {
        if (Notifier::updateCheckDue($request->user())) {
            Notifier::checkForUpdate();
        }

        return response()->noContent();
    }

    private function message(AdminNotification $n): HtmlString
    {
        $repeats = $n->count > 1 ? ' <span class="badge badge-muted">×'.(int) $n->count.'</span>' : '';
        $hint = $n->type === 'error' ? '<div class="muted small">'.e(__('Details are in storage/logs/laravel.log.')).'</div>' : '';
        $body = $n->body ? '<div class="muted small">'.e($n->body).'</div>' : '';
        $weight = $n->is_read ? '' : ' style="font-weight:600"';

        return new HtmlString('<a'.$weight.' href="'.e(route('admin.notifications.open', $n->id)).'">'.e($n->titleText()).'</a>'.$repeats.$body.$hint);
    }
}
