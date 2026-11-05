<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Comment;
use App\Models\Post;
use App\Support\BackupService;
use App\Support\Locales;
use App\Support\SiteHealth;

/** What needs doing and how the site is doing. Each user only sees what they can open. */
class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $own = $user->can('articles.manage') ? null : $user->id;
        $articles = fn () => Post::where('type', 'article')->when($own, fn ($q) => $q->where('user_id', $own));
        $health = $user->can('settings.manage') ? collect(rescue(fn () => SiteHealth::checks(), [], false))->countBy('status') : null;

        return view('admin.dashboard', [
            'title' => __('Dashboard'),
            'actions' => $this->actions(),
            'attention' => $this->attention($articles, $health),
            'stats' => $this->stats($articles, $own),
            'recent' => $articles()->latest('updated_at')->limit(5)->get(),
            'scheduled' => $articles()->where('status', 'published')->where('published_at', '>', now())->oldest('published_at')->limit(5)->get(),
            'comments' => $user->can('comments.manage') ? Comment::with(['post' => fn ($q) => $q->withTrashed()])->latest('id')->limit(5)->get() : collect(),
            'popular' => Post::where('view_count', '>', 0)->when($own, fn ($q) => $q->where('user_id', $own))
                ->when(! $user->can('pages.manage'), fn ($q) => $q->where('type', 'article'))
                ->orderByDesc('view_count')->limit(5)->get(),
            'activities' => $user->can('logs.view') ? ActivityLog::latest('id')->limit(6)->get() : collect(),
            'health' => $health,
            'latestBackup' => $user->can('backups.manage') ? BackupService::all()->max('created') : null,
        ]);
    }

    private function actions(): array
    {
        $user = auth()->user();

        return array_values(array_filter([
            $user->can('media.manage') ? ['label' => __('Upload'), 'icon' => 'upload', 'secondary' => true, 'sheet' => route('admin.media.upload')] : null,
            $user->can('pages.manage') ? ['label' => __('New page'), 'secondary' => true, 'url' => route('admin.pages.create')] : null,
            $user->can('articles.write') ? ['label' => __('New article'), 'url' => route('admin.articles.create')] : null,
        ]));
    }

    /** @return list<array{tone: string, text: string, label: string, url: string}> Worst first. */
    private function attention(callable $articles, $health): array
    {
        $user = auth()->user();
        $items = [];

        if ($critical = $health['critical'] ?? 0) {
            $items[] = ['tone' => 'danger', 'text' => __(':count site health check(s) need attention now.', ['count' => $critical]), 'label' => __('Site health'), 'url' => route('admin.settings.health.index')];
        }
        // The notification table can be missing mid-update; the dashboard must still open.
        if ($user->can('updates.manage') && ($update = rescue(fn () => AdminNotification::where('type', 'update')->first(), null, false))) {
            $items[] = ['tone' => 'warning', 'text' => __('Version :version is available', $update->params ?? []), 'label' => __('Update'), 'url' => route('admin.settings.update.index', ['check' => 1])];
        }
        if ($user->can('comments.manage') && ($pending = Comment::where('status', 'pending')->count())) {
            $items[] = ['tone' => 'warning', 'text' => __(':count comment(s) waiting for approval.', ['count' => $pending]), 'label' => __('Comments'), 'url' => route('admin.comments.index', ['f' => ['status' => 'pending']])];
        }
        if ($warnings = $health['warning'] ?? 0) {
            $items[] = ['tone' => 'info', 'text' => __(':count thing(s) to improve in site health.', ['count' => $warnings]), 'label' => __('Site health'), 'url' => route('admin.settings.health.index')];
        }

        $second = Locales::second();
        $untranslated = $articles()->live()->whereDoesntHave('translations', fn ($q) => $q->where('locale', $second)->where('status', 'published'))->count();
        if ($untranslated) {
            $items[] = ['tone' => 'info', 'text' => __(':count published article(s) have no :language version.', ['count' => $untranslated, 'language' => Locales::name($second)]), 'label' => __('Articles'), 'url' => route('admin.articles.index')];
        }

        return $items;
    }

    private function stats(callable $articles, ?int $own): array
    {
        $user = auth()->user();
        $scheduled = $articles()->where('status', 'published')->where('published_at', '>', now())->count();
        $recent = $articles()->live()->where('published_at', '>=', now()->subDays(7))->count();

        $stats = [];
        if ($user->can('articles.write')) {
            $stats[] = ['label' => __('Published articles'), 'icon' => 'article', 'value' => $articles()->live()->count(), 'url' => route('admin.articles.index', ['f' => ['status' => 'published']]),
                'hint' => $scheduled ? __(':count in the last 7 days, :scheduled scheduled', ['count' => $recent, 'scheduled' => $scheduled]) : __(':count in the last 7 days', ['count' => $recent])];
            $stats[] = ['label' => __('Draft articles'), 'icon' => 'pencil', 'value' => $articles()->where('status', 'draft')->count(), 'url' => route('admin.articles.index', ['f' => ['status' => 'draft']]), 'hint' => __('Not published yet')];
        }
        if ($user->can('pages.manage')) {
            $stats[] = ['label' => __('Pages'), 'icon' => 'page', 'value' => Post::where('type', 'page')->count(), 'url' => route('admin.pages.index'),
                'hint' => __(':count draft', ['count' => Post::where('type', 'page')->where('status', 'draft')->count()])];
        }
        if ($user->can('comments.manage')) {
            $stats[] = ['label' => __('Pending comments'), 'icon' => 'comment', 'value' => Comment::where('status', 'pending')->count(), 'url' => route('admin.comments.index', ['f' => ['status' => 'pending']]),
                'hint' => __(':count approved', ['count' => Comment::where('status', 'approved')->count()])];
        }
        $stats[] = ['label' => __('Total views'), 'icon' => 'activity', 'value' => (int) Post::when($own, fn ($q) => $q->where('user_id', $own))->sum('view_count'), 'url' => null, 'hint' => __('Articles and pages, all languages')];

        return $stats;
    }
}
