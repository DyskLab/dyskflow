<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/views');
        $this->app->useStoragePath($storage);
        file_put_contents($storage.'/app/.installed', 'test');

        $this->admin = User::create(['role_id' => Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true])->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        $this->author = User::create(['role_id' => Role::create(['name' => 'Author', 'slug' => 'author', 'capabilities' => ['articles.write']])->id, 'name' => 'Author', 'email' => 'author@example.test', 'password' => 'password123']);
        Setting::write(['locale' => 'en', 'site_title' => 'Test Site', 'site_url' => 'http://localhost']);
    }

    private function article(string $slug, array $extra = []): Post
    {
        return Post::create($extra + ['user_id' => $this->admin->id, 'type' => 'article', 'title' => 'Title '.$slug, 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subMonth()]);
    }

    private function stat(string $label): int
    {
        $stat = collect($this->get('/admin')->viewData('stats'))->firstWhere('label', $label);

        return $stat['value'];
    }

    public function test_counts_leave_out_scheduled_and_trashed_articles(): void
    {
        $this->article('live');
        $this->article('later', ['published_at' => now()->addDay()]);
        $this->article('gone')->delete();
        $this->actingAs($this->admin);

        $this->assertSame(1, $this->stat('Published articles'));
        $this->get('/admin')->assertSee('Scheduled to publish')->assertSee('Title later')->assertDontSee('Title gone');
    }

    public function test_attention_lists_what_needs_doing_with_links(): void
    {
        $post = $this->article('live');
        $post->translations()->create(['type' => 'article', 'locale' => 'id', 'title' => 'Judul', 'slug' => 'judul', 'status' => 'published']);
        $post->comments()->create(['author_name' => 'Budi', 'content' => 'Hello there', 'status' => 'pending']);
        AdminNotification::create(['type' => 'update', 'title' => 'Version :version is available', 'params' => ['version' => '9.9.9'], 'capability' => 'updates.manage']);
        Config::set('app.debug', true);

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('1 comment(s) waiting for approval.')
            ->assertSee('Version 9.9.9 is available')
            ->assertSee('site health check(s) need attention now.')
            ->assertSee('Budi')
            ->assertSee('Site status');

        $tones = array_column($this->get('/admin')->viewData('attention'), 'tone');
        $this->assertSame('danger', $tones[0]);
    }

    public function test_an_article_without_a_translation_is_flagged(): void
    {
        $this->article('live');

        $this->actingAs($this->author)->get('/admin')->assertDontSee('have no');
        $this->actingAs($this->admin)->get('/admin')->assertSee('1 published article(s) have no');
    }

    public function test_authors_see_only_their_own_work(): void
    {
        $theirs = $this->article('theirs', ['view_count' => 50]);
        $theirs->comments()->create(['author_name' => 'Budi', 'content' => 'Hello there', 'status' => 'pending']);
        $this->article('mine', ['user_id' => $this->author->id, 'view_count' => 7]);
        AdminNotification::create(['type' => 'update', 'title' => 'Version :version is available', 'params' => ['version' => '9.9.9'], 'capability' => 'updates.manage']);

        $response = $this->actingAs($this->author)->get('/admin')->assertOk()
            ->assertSee('Title mine')
            ->assertDontSee('Title theirs')
            ->assertDontSee('Latest comments')
            ->assertDontSee('Site status')
            ->assertDontSee('waiting for approval')
            ->assertDontSee('site health');

        $this->assertSame(['Published articles', 'Draft articles', 'Total views'], array_column($response->viewData('stats'), 'label'));
        $this->assertSame(7, $this->stat('Total views'));
    }

    public function test_a_quiet_site_says_so(): void
    {
        $response = $this->actingAs($this->author)->get('/admin')->assertOk()
            ->assertSee('Nothing needs your attention right now.')
            ->assertSee('No articles yet.')
            ->assertSee('No views yet.');

        $this->assertSame([], $response->viewData('attention'));
    }

    public function test_every_link_opens_for_the_user_who_sees_it(): void
    {
        $this->article('live', ['view_count' => 3]);
        $this->actingAs($this->admin);

        $links = collect($this->get('/admin')->viewData('stats'))->pluck('url')->filter();
        foreach ($links as $url) {
            $this->get($url)->assertOk();
        }
    }
}
