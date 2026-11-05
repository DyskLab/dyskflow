<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Support\DataTable;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    public function index()
    {
        $statuses = ['pending' => __('Pending'), 'approved' => __('Approved'), 'spam' => __('Spam')];
        $bulk = route('admin.comments.bulk');

        return view('admin.table', [
            'title' => __('Comments'),
            'table' => DataTable::make([
                'id' => 'comments',
                'source' => Comment::query()->with('post'),
                'columns' => [
                    ['key' => 'author_name', 'label' => __('Author'), 'sort' => true, 'render' => fn ($c) => new HtmlString('<div style="font-weight:500">'.e($c->author_name).'</div><div class="muted small">'.e((string) $c->author_email).'</div>')],
                    ['key' => 'content', 'label' => __('Comment'), 'render' => fn ($c) => Str::limit($c->content, 120)],
                    ['key' => 'post', 'label' => __('Article'), 'render' => fn ($c) => $c->post?->title ?? '—'],
                    ['key' => 'status', 'label' => __('Status'), 'sort' => true, 'render' => fn ($c) => new HtmlString('<span class="badge '.['pending' => 'badge-warning', 'approved' => 'badge-success', 'spam' => 'badge-danger'][$c->status].'">'.e($statuses[$c->status]).'</span>')],
                    ['key' => 'created_at', 'label' => __('Date'), 'sort' => true, 'render' => fn ($c) => cms_date($c->created_at)],
                ],
                'search' => ['author_name', 'author_email', 'content'],
                'filters' => ['status' => ['label' => __('Status'), 'options' => $statuses]],
                'default_sort' => ['created_at', 'desc'],
                'bulk_url' => $bulk,
                'bulk' => [
                    'approved' => ['label' => __('Approve')],
                    'pending' => ['label' => __('Mark as pending')],
                    'spam' => ['label' => __('Mark as spam')],
                    'delete' => ['label' => __('Delete'), 'danger' => true],
                ],
                'row_actions' => fn ($c) => [
                    $c->status !== 'approved' ? ['label' => __('Approve'), 'icon' => 'check', 'post' => $bulk, 'fields' => ['ids[]' => [$c->id], 'action' => 'approved']] : null,
                    $c->status !== 'spam' ? ['label' => __('Mark as spam'), 'icon' => 'ban', 'post' => $bulk, 'fields' => ['ids[]' => [$c->id], 'action' => 'spam']] : null,
                    ['label' => __('Edit'), 'icon' => 'pencil', 'sheet' => route('admin.comments.edit', $c)],
                    ['label' => __('Delete'), 'icon' => 'trash', 'post' => $bulk, 'fields' => ['ids[]' => [$c->id], 'action' => 'delete'], 'danger' => true, 'confirm' => __('This action cannot be undone.')],
                ],
            ]),
        ]);
    }

    public function edit(Comment $comment)
    {
        return view('admin.partials.sheet-form', [
            'title' => __('Edit comment'),
            'description' => $comment->post?->title,
            'action' => route('admin.comments.update', $comment),
            'values' => $comment->toArray(),
            'fields' => [
                ['name' => 'author_name', 'label' => __('Name'), 'required' => true],
                ['name' => 'author_email', 'label' => __('Email'), 'type' => 'email'],
                ['name' => 'author_url', 'label' => __('Website'), 'type' => 'url'],
                ['name' => 'content', 'label' => __('Comment'), 'type' => 'textarea', 'rows' => 6, 'required' => true],
                ['name' => 'status', 'label' => __('Status'), 'type' => 'select', 'options' => ['pending' => __('Pending'), 'approved' => __('Approved'), 'spam' => __('Spam')]],
            ],
        ]);
    }

    public function update(Request $request, Comment $comment)
    {
        $comment->update($request->validate([
            'author_name' => 'required|string|max:190',
            'author_email' => 'nullable|email|max:190',
            'author_url' => 'nullable|url:http,https|max:190',
            'content' => 'required|string|max:5000',
            'status' => 'required|in:pending,approved,spam',
        ]));
        activity('comment.updated', $comment->author_name);

        return back()->with('success', __('Saved.'));
    }

    public function bulk(Request $request)
    {
        $action = $request->validate(['action' => 'required|in:approved,pending,spam,delete'])['action'];
        $query = Comment::whereIn('id', $this->ids($request));
        $count = $action === 'delete' ? $query->delete() : $query->update(['status' => $action]);
        activity("comment.$action", (string) $count);

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }
}
