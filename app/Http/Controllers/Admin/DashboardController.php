<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $own = $user->can('articles.manage') ? null : $user->id;

        return view('admin.dashboard', [
            'title' => __('Dashboard'),
            'stats' => [
                [__('Published articles'), Post::where('type', 'article')->where('status', 'published')->when($own, fn ($q) => $q->where('user_id', $own))->count()],
                [__('Draft articles'), Post::where('type', 'article')->where('status', 'draft')->when($own, fn ($q) => $q->where('user_id', $own))->count()],
                [__('Pages'), Post::where('type', 'page')->count()],
                [__('Pending comments'), Comment::where('status', 'pending')->count()],
                [__('Media files'), Media::count()],
            ],
            'recent' => Post::where('type', 'article')->when($own, fn ($q) => $q->where('user_id', $own))->latest('updated_at')->limit(5)->get(),
            'activities' => $user->can('logs.view') ? ActivityLog::latest('id')->limit(6)->get() : collect(),
        ]);
    }
}
