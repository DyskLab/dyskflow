<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationTest extends TestCase
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
        Setting::write(['locale' => 'en', 'site_title' => 'Test Site']);
        // The app registers this only when already installed at boot, which depends on the machine.
        Event::forget(MessageLogged::class);
        Event::listen(MessageLogged::class, [Notifier::class, 'logged']);
    }

    private function comment(string $status = 'pending'): Comment
    {
        $post = Post::create(['user_id' => $this->admin->id, 'type' => 'article', 'title' => 'Hello', 'slug' => 'hello-'.uniqid(), 'status' => 'published']);

        return $post->comments()->create(['author_name' => 'Budi', 'author_email' => 'b@example.test', 'content' => 'Nice article', 'status' => $status]);
    }

    public function test_a_new_comment_notifies_those_who_manage_comments_only(): void
    {
        $this->comment();

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('Comment from Budi on “Hello” is awaiting approval')
            ->assertSee('Notifications, 1 unread');
        $this->actingAs($this->author)->get('/admin/articles')->assertOk()
            ->assertDontSee('Comment from Budi')
            ->assertSee('No notifications yet.');
    }

    public function test_spam_comments_do_not_notify(): void
    {
        $this->comment('spam');

        $this->assertSame(0, AdminNotification::count());
    }

    public function test_opening_marks_read_for_that_user_and_goes_to_the_target(): void
    {
        $this->comment();
        $id = AdminNotification::first()->id;
        $other = User::create(['role_id' => $this->admin->role_id, 'name' => 'Second', 'email' => 'second@example.test', 'password' => 'password123']);

        $this->actingAs($this->admin)->get("/admin/notifications/$id/open")->assertRedirect(url('admin/comments'));

        $this->assertSame(0, AdminNotification::unreadBy($this->admin)->count());
        $this->assertSame(1, AdminNotification::unreadBy($other)->count());
    }

    public function test_a_user_cannot_open_or_delete_a_notification_they_cannot_see(): void
    {
        $this->comment();
        $id = AdminNotification::first()->id;

        $this->actingAs($this->author)->get("/admin/notifications/$id/open")->assertNotFound();
        $this->actingAs($this->author)->post('/admin/notifications/bulk', ['action' => 'delete', 'ids' => [$id]]);

        $this->assertSame(1, AdminNotification::count());
    }

    public function test_the_list_page_and_bulk_actions_work(): void
    {
        $this->comment();
        $id = AdminNotification::first()->id;

        $this->actingAs($this->admin)->get('/admin/notifications')->assertOk()->assertSee('Nice article');
        $this->actingAs($this->admin)->post('/admin/notifications/bulk', ['action' => 'read', 'ids' => [$id]])->assertRedirect();
        $this->assertSame(0, AdminNotification::unreadBy($this->admin)->count());
        $this->actingAs($this->admin)->get('/admin/notifications?f[state]=unread')->assertOk()->assertSee('dt-empty', false);

        $this->actingAs($this->admin)->post('/admin/notifications/bulk', ['action' => 'delete', 'ids' => [$id]]);
        $this->assertSame(0, AdminNotification::count());
    }

    public function test_mark_all_read(): void
    {
        $this->comment();
        $this->comment();

        $this->actingAs($this->admin)->post('/admin/notifications/read-all')->assertRedirect();

        $this->assertSame(0, AdminNotification::unreadBy($this->admin)->count());
    }

    public function test_a_repeated_error_stays_on_one_row_and_becomes_unread_again(): void
    {
        $exception = new \RuntimeException('Disk is full');
        Log::error($exception->getMessage(), ['exception' => $exception]);
        $notification = AdminNotification::where('type', 'error')->sole();
        $notification->markReadBy($this->admin);

        Log::error($exception->getMessage(), ['exception' => $exception]);

        $this->assertSame(2, $notification->refresh()->count);
        $this->assertSame(1, AdminNotification::unreadBy($this->admin)->count());
        $this->actingAs($this->author)->get('/admin/articles')->assertDontSee('Disk is full');
        $this->actingAs($this->admin)->get('/admin')->assertSee('Disk is full');
    }

    public function test_warnings_do_not_notify(): void
    {
        Log::warning('Just a warning');

        $this->assertSame(0, AdminNotification::count());
    }

    private function fakeRelease(string $tag): void
    {
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => $tag, 'body' => '', 'published_at' => null, 'html_url' => null])]);
    }

    public function test_an_update_check_runs_when_due_and_keeps_one_notification(): void
    {
        Setting::write(['update_repo' => 'acme/cms']);
        $this->fakeRelease('v99.0.0');

        $this->actingAs($this->admin)->get('/admin')->assertSee('data-check-url', false);
        $this->actingAs($this->admin)->get('/admin/notifications/check')->assertNoContent();
        $this->assertSame('Version :version is available', AdminNotification::where('type', 'update')->sole()->title);

        // Checked just now, so no new check is requested and the endpoint stays quiet.
        $this->actingAs($this->admin)->get('/admin')->assertDontSee('data-check-url', false);
        Http::assertSentCount(1);
        $this->actingAs($this->admin)->get('/admin/notifications/check')->assertNoContent();
        Http::assertSentCount(1);

        // A newer release replaces it, and being up to date removes it.
        Notifier::syncUpdate(['latest' => '99.1.0', 'available' => true]);
        $this->assertSame(['version' => '99.1.0'], AdminNotification::where('type', 'update')->sole()->params);
        Notifier::syncUpdate(['latest' => '99.1.0', 'available' => false]);
        $this->assertSame(0, AdminNotification::where('type', 'update')->count());
    }

    public function test_a_failed_update_check_stays_silent(): void
    {
        Setting::write(['update_repo' => 'acme/cms']);
        Http::fake(['api.github.com/*' => Http::response([], 500)]);

        $this->actingAs($this->admin)->get('/admin/notifications/check')->assertNoContent();

        $this->assertSame(0, AdminNotification::count());
    }

    public function test_only_those_who_manage_updates_trigger_checks(): void
    {
        Setting::write(['update_repo' => 'acme/cms']);
        Http::fake();

        $this->actingAs($this->author)->get('/admin/articles')->assertDontSee('data-check-url', false);
        $this->actingAs($this->author)->get('/admin/notifications/check')->assertNoContent();

        Http::assertNothingSent();
    }

    public function test_missing_tables_do_not_break_the_admin_or_a_comment(): void
    {
        Schema::drop('admin_notification_reads');
        Schema::drop('admin_notifications');

        $this->comment();
        $this->actingAs($this->admin)->get('/admin')->assertOk();

        $this->assertSame(1, Comment::count());
    }

    public function test_the_dyskflow_landing_page_renders_without_cookies(): void
    {
        Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'About DyskFlow', 'slug' => 'about-dyskflow', 'status' => 'published', 'landing' => 'dyskflow']);

        $response = $this->get('/about-dyskflow')->assertOk()
            ->assertSee('What makes it different')
            ->assertSee('<title>', false)
            ->assertSee('_landing/dyskflow/assets/style.css', false);

        $this->assertEmpty($response->headers->getCookies());
        $this->get('/_landing/dyskflow/assets/style.css')->assertOk();
    }
}
