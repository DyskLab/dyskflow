<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Setting;
use App\Support\Locales;
use App\Support\PrivacyGuide;
use App\Support\SiteHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteToolsController extends Controller
{
    public function privacy()
    {
        return view('admin.privacy', [
            'title' => __('Privacy guide'),
            'page' => $this->privacyPage(),
            'facts' => PrivacyGuide::facts(),
            'checklist' => PrivacyGuide::checklist(),
        ]);
    }

    public function privacyDraft(Request $request)
    {
        if ($page = $this->privacyPage()) {
            return redirect()->route('admin.pages.edit', $page);
        }

        $locale = Locales::default();
        $title = __('Privacy Policy', [], $locale);
        $base = Str::slug($title);
        $slug = $base;
        for ($i = 2; Post::withTrashed()->where(['type' => 'page', 'slug' => $slug])->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        $page = Post::create([
            'user_id' => $request->user()->id,
            'type' => 'page',
            'title' => $title,
            'slug' => $slug,
            'content' => PrivacyGuide::draft($locale),
            'status' => 'draft',
            'allow_comments' => false,
        ]);
        Setting::write(['privacy_page_id' => (string) $page->id]);
        activity('page.created', $page->title);

        return redirect()->route('admin.pages.edit', $page)->with('success', __('Draft created. Replace the words in square brackets, then publish.'));
    }

    public function health()
    {
        $checks = collect(SiteHealth::checks());

        return view('admin.health', [
            'title' => __('Site health'),
            'checks' => $checks,
            'counts' => $checks->countBy('status'),
        ]);
    }

    private function privacyPage(): ?Post
    {
        $id = setting('privacy_page_id');

        return $id ? Post::where('type', 'page')->find($id) : null;
    }
}
