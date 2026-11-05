<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TrashAndViewsTest extends TestCase
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

    private function article(string $slug = 'hello', array $extra = []): Post
    {
        return Post::create($extra + ['user_id' => $this->admin->id, 'type' => 'article', 'title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    public function test_a_trashed_article_leaves_the_site_and_comes_back_on_restore(): void
    {
        $post = $this->article();
        $this->get('/blog/hello')->assertOk();

        $this->actingAs($this->admin)->post('/admin/articles/bulk', ['ids' => [$post->id], 'action' => 'delete']);
        $this->assertSoftDeleted($post);

        $this->get('/admin/articles')->assertDontSee('/blog/hello')->assertSee('Trash (1)');
        $this->get('/admin/articles?f[status]=trash')->assertOk()->assertSee('/blog/hello')->assertSee('Delete permanently');
        $this->get("/admin/articles/{$post->id}/edit")->assertNotFound();

        auth()->logout();
        $this->get('/blog/hello')->assertNotFound();
        $this->get('/blog')->assertDontSee('Hello');
        $this->get('/sitemap.xml')->assertDontSee('blog/hello');

        $this->actingAs($this->admin)->post('/admin/articles/bulk', ['ids' => [$post->id], 'action' => 'restore']);
        auth()->logout();
        $this->get('/blog/hello')->assertOk();
    }

    public function test_permanent_delete_only_touches_the_trash_and_takes_the_comments(): void
    {
        $post = $this->article();
        $post->comments()->create(['author_name' => 'A', 'content' => 'Hi there', 'status' => 'approved']);
        $this->actingAs($this->admin);

        $this->post('/admin/articles/bulk', ['ids' => [$post->id], 'action' => 'force']);
        $this->assertNotSoftDeleted($post);

        $post->delete();
        $this->post('/admin/articles/bulk', ['ids' => [$post->id], 'action' => 'force']);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_authors_can_only_restore_their_own(): void
    {
        $theirs = $this->article('theirs');
        $mine = $this->article('mine', ['user_id' => $this->author->id]);
        $theirs->delete();
        $mine->delete();

        $this->actingAs($this->author)->post('/admin/articles/bulk', ['ids' => [$theirs->id, $mine->id], 'action' => 'restore']);

        $this->assertSoftDeleted($theirs);
        $this->assertNotSoftDeleted($mine);
        $this->get('/admin/articles')->assertDontSee('Trash (');
    }

    public function test_a_trashed_post_keeps_its_slug_and_pages_share_the_trash(): void
    {
        $this->article()->delete();

        $this->actingAs($this->admin)->post('/admin/articles', ['title' => 'Hello', 'status' => 'draft'])->assertRedirect();
        $this->assertDatabaseHas('posts', ['slug' => 'hello-2', 'type' => 'article']);

        $page = Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'About', 'slug' => 'about', 'status' => 'published']);
        $this->post('/admin/pages/bulk', ['ids' => [$page->id], 'action' => 'delete']);
        $this->assertSoftDeleted($page);
        $this->get('/admin/pages?f[status]=trash')->assertOk()->assertSee('/about');
    }

    public function test_a_trashed_homepage_falls_back_to_the_article_list(): void
    {
        $page = Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'Front', 'slug' => 'front', 'status' => 'published', 'content' => 'Front words']);
        Setting::write(['home_type' => 'page', 'home_page_id' => (string) $page->id]);
        $this->get('/')->assertOk()->assertSee('Front words');

        $page->delete();

        $this->get('/')->assertOk()->assertDontSee('Front words');
    }

    public function test_comments_can_be_trashed_restored_and_deleted(): void
    {
        $post = $this->article();
        $comment = $post->comments()->create(['author_name' => 'Budi', 'content' => 'Nice words here', 'status' => 'approved']);
        $this->get('/blog/hello')->assertSee('Nice words here');
        $this->actingAs($this->admin);

        $this->post('/admin/comments/bulk', ['ids' => [$comment->id], 'action' => 'delete']);
        $this->assertSoftDeleted($comment);
        // The header notification still quotes the comment, so check the table itself.
        $this->get('/admin/comments')->assertSee('No data yet.')->assertSee('Trash (1)');
        $this->get('/admin/comments?f[status]=trash')->assertOk()->assertSee('Nice words here');
        $this->post('/admin/comments/bulk', ['ids' => [$comment->id], 'action' => 'approved']);
        $this->assertSoftDeleted($comment);

        $this->post('/admin/comments/bulk', ['ids' => [$comment->id], 'action' => 'restore']);
        $this->assertNotSoftDeleted($comment);
        auth()->logout();
        $this->get('/blog/hello')->assertSee('Nice words here');

        $comment->delete();
        $this->actingAs($this->admin)->post('/admin/comments/bulk', ['ids' => [$comment->id], 'action' => 'force']);
        $this->assertSame(0, Comment::withTrashed()->count());
    }

    public function test_a_comment_on_a_trashed_article_still_names_the_article(): void
    {
        $post = $this->article('named', ['title' => 'Named article']);
        $post->comments()->create(['author_name' => 'A', 'content' => 'Hi there', 'status' => 'pending']);
        $post->delete();

        $this->actingAs($this->admin)->get('/admin/comments')->assertOk()->assertSee('Named article');
    }

    public function test_visits_count_without_changing_the_update_date_or_sending_cookies(): void
    {
        $post = $this->article('hello', ['allow_comments' => false]);
        DB::table('posts')->where('id', $post->id)->update(['updated_at' => '2026-01-01 00:00:00']);

        $this->get('/blog/hello')->assertOk()->assertSee('Views: 1')->assertCookieMissing(config('session.cookie'));
        $this->get('/blog/hello')->assertSee('Views: 2');

        $post->refresh();
        $this->assertSame(2, (int) $post->view_count);
        $this->assertSame('2026-01-01 00:00:00', $post->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_staff_bots_previews_and_drafts_do_not_count(): void
    {
        $post = $this->article();
        $draft = $this->article('draft', ['status' => 'draft']);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get('/blog/hello')->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0')->withHeader('Sec-Purpose', 'prefetch')->get('/blog/hello')->assertOk();
        $this->flushHeaders();
        $this->actingAs($this->admin)->get('/blog/hello')->assertOk();
        $this->get('/blog/draft')->assertOk();

        $this->assertSame(0, (int) $post->fresh()->view_count);
        $this->assertSame(0, (int) $draft->fresh()->view_count);
    }

    public function test_pages_and_a_homepage_count_and_all_languages_add_up(): void
    {
        $page = Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'About', 'slug' => 'about', 'status' => 'published']);
        $page->translations()->create(['type' => 'page', 'locale' => 'id', 'title' => 'Tentang', 'slug' => 'tentang', 'status' => 'published']);

        $this->get('/about')->assertOk();
        $this->get('/id/tentang')->assertOk();
        $this->assertSame(2, (int) $page->fresh()->view_count);

        Setting::write(['home_type' => 'page', 'home_page_id' => (string) $page->id]);
        $this->get('/')->assertOk();
        $this->assertSame(3, (int) $page->fresh()->view_count);
    }

    public function test_the_count_can_be_hidden_and_shows_in_admin(): void
    {
        $post = $this->article();
        Setting::write(['show_view_count' => '0']);
        $this->get('/blog/hello')->assertOk()->assertDontSee('Views:');
        $this->assertSame(1, (int) $post->fresh()->view_count);

        $this->actingAs($this->admin)->get('/admin/articles?sort=view_count&dir=desc')->assertOk()->assertSee('Views');
        $this->get('/admin')->assertOk()->assertSee('Most viewed')->assertSee('Total views');
    }
}
