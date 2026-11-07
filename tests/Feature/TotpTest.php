<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Term;
use App\Models\User;
use App\Support\Totp;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TotpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/views');
        $this->app->useStoragePath($storage);
        file_put_contents($storage.'/app/.installed', 'test');

        $role = Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true]);
        $this->admin = User::create(['role_id' => $role->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        Setting::write(['locale' => 'en', 'site_title' => 'Test Site']);
    }

    private function currentCode(string $secret): string
    {
        return Totp::code($secret, (int) floor(time() / 30));
    }

    private function enable(): string
    {
        $secret = Totp::generateSecret();
        // A step in the past, so the current code is still unused.
        $this->admin->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => now(), 'totp_last_step' => 1])->save();

        return $secret;
    }

    private function logInWithPassword(): void
    {
        $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'password123']);
    }

    public function test_codes_match_the_rfc_6238_test_vectors(): void
    {
        // Base32 of "12345678901234567890". The RFC lists 8 digits; these are the last 6.
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', Totp::code($secret, (int) floor(59 / 30)));
        $this->assertSame('081804', Totp::code($secret, (int) floor(1111111109 / 30)));
        $this->assertSame('005924', Totp::code($secret, (int) floor(1234567890 / 30)));
        $this->assertSame('279037', Totp::code($secret, (int) floor(2000000000 / 30)));
    }

    public function test_verify_allows_one_step_of_drift_and_refuses_replays(): void
    {
        $secret = Totp::generateSecret();
        $this->assertSame(32, strlen($secret));
        $time = 1800000000;
        $step = (int) floor($time / 30);

        $this->assertSame($step + 1, Totp::verify($secret, Totp::code($secret, $step + 1), null, $time));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $step + 2), null, $time));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $step), $step, $time));
        $this->assertNull(Totp::verify($secret, 'abcdef', null, $time));
    }

    public function test_an_alert_shows_until_totp_is_on(): void
    {
        $text = 'Your account is protected by a password only';
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee($text);
        $this->actingAs($this->admin)->get('/admin/account/security')->assertOk()->assertDontSee($text);

        $this->enable();
        $this->actingAs($this->admin->refresh())->get('/admin')->assertOk()->assertDontSee($text);
    }

    public function test_turning_on_needs_the_password_and_a_valid_code(): void
    {
        $this->actingAs($this->admin)->get('/admin/account/security/setup')->assertOk()->assertSee('<svg', false);
        $secret = session('totp.pending');

        $this->post('/admin/account/security/setup', ['password' => 'wrong', 'code' => $this->currentCode($secret)])->assertSessionHasErrors('password');
        $this->post('/admin/account/security/setup', ['password' => 'password123', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($this->admin->refresh()->hasTotp());

        $this->post('/admin/account/security/setup', ['password' => 'password123', 'code' => $this->currentCode($secret)])
            ->assertRedirect(route('admin.account.security.index'))
            ->assertSessionHas('recovery_codes');

        $this->assertTrue($this->admin->refresh()->hasTotp());
        $this->assertSame($secret, $this->admin->totp_secret);
        $this->assertNotSame($secret, $this->admin->getRawOriginal('totp_secret'));
    }

    public function test_login_stops_at_the_code_until_it_is_correct(): void
    {
        $secret = $this->enable();

        $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'password123'])->assertRedirect(route('login.totp'));
        $this->assertGuest();
        $this->get('/admin')->assertRedirect(route('login'));

        $this->post('/admin/login/totp', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/admin/login/totp', ['code' => $this->currentCode($secret)])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_a_used_code_cannot_log_in_twice(): void
    {
        $code = $this->currentCode($this->enable());

        $this->logInWithPassword();
        $this->post('/admin/login/totp', ['code' => $code]);
        $this->post('/admin/logout');

        $this->logInWithPassword();
        $this->post('/admin/login/totp', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_the_code_screen_needs_the_password_first(): void
    {
        $this->get('/admin/login/totp')->assertRedirect(route('login'));
        $this->post('/admin/login/totp', ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_guesses_are_limited_per_account(): void
    {
        $secret = $this->enable();
        $this->logInWithPassword();
        foreach (range(1, 5) as $ignored) {
            $this->post('/admin/login/totp', ['code' => '000000']);
        }

        $this->post('/admin/login/totp', ['code' => $this->currentCode($secret)])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $this->enable();
        $code = $this->admin->newRecoveryCodes()[0];

        $this->logInWithPassword();
        $this->post('/admin/login/totp', ['code' => strtoupper($code)])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin);
        $this->post('/admin/logout');

        $this->logInWithPassword();
        $this->post('/admin/login/totp', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_secret_from_another_app_key_fails_without_an_error(): void
    {
        $this->enable();
        DB::table('users')->where('id', $this->admin->id)->update(['totp_secret' => 'not-decryptable']);

        $this->logInWithPassword();
        $this->post('/admin/login/totp', ['code' => '123456'])->assertSessionHasErrors('code');
    }

    public function test_turning_off_needs_password_and_code(): void
    {
        $secret = $this->enable();

        $this->actingAs($this->admin)->post('/admin/account/security/disable', ['password' => 'wrong', 'code' => $this->currentCode($secret)])->assertSessionHasErrors('password');
        $this->post('/admin/account/security/disable', ['password' => 'password123', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->assertTrue($this->admin->refresh()->hasTotp());

        $this->post('/admin/account/security/disable', ['password' => 'password123', 'code' => $this->currentCode($secret)])->assertRedirect();
        $this->assertFalse($this->admin->refresh()->hasTotp());
        $this->assertNull($this->admin->totp_secret);
    }

    public function test_an_admin_can_reset_another_user_and_the_command_works(): void
    {
        $this->enable();
        $other = User::create(['role_id' => $this->admin->role_id, 'name' => 'Second', 'email' => 'second@example.test', 'password' => 'password123']);

        $this->actingAs($other)->get('/admin/users')->assertOk()->assertSee('Reset TOTP');
        $this->post('/admin/users/bulk', ['action' => 'reset_totp', 'ids' => [$this->admin->id]]);
        $this->assertFalse($this->admin->refresh()->hasTotp());

        $this->enable();
        $this->artisan('cms:totp-reset', ['email' => 'admin@example.test'])->assertExitCode(0);
        $this->assertFalse($this->admin->refresh()->hasTotp());
        $this->artisan('cms:totp-reset', ['email' => 'nobody@example.test'])->assertExitCode(1);
    }

    public function test_the_admin_footer_links_to_dysklab(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertSee('https://www.fanny.dev/dysklab', false);
    }

    public function test_the_seeder_fills_every_kind_of_content_and_can_run_twice(): void
    {
        // The seeder writes under the public folder. Point it elsewhere so a test run never touches this install's uploads.
        $public = sys_get_temp_dir().'/cms-public-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($public.'/img');
        File::copy(public_path('img/logo-mark.svg'), $public.'/img/logo-mark.svg');
        $this->app->usePublicPath($public);

        try {
            $this->seed(DatabaseSeeder::class);
            $this->seed(DatabaseSeeder::class);

            $this->assertSame(2, Post::where('type', 'article')->count());
            $this->assertSame(1, Post::where('type', 'page')->count());
            $this->assertSame(2, Term::where('taxonomy', 'category')->count());
            $this->assertSame(2, Term::where('taxonomy', 'tag')->count());
            $this->assertSame(2, Comment::count());
            $this->assertSame(1, Media::count());

            // The base language is English in these tests, so Indonesian is the translation.
            $this->get('/blog')->assertOk()->assertSee('Welcome to DyskFlow')->assertSee('post-thumb', false);
            $this->get('/blog/welcome-to-dyskflow')->assertOk();
            $this->get('/id/blog/selamat-datang-di-dyskflow')->assertOk()->assertSee('Selamat datang di DyskFlow');
            $this->get('/category/guides')->assertOk();
            $this->get('/kontak')->assertOk();

            // The privacy policy waits as a draft until the owner fills in the brackets.
            $privacy = Post::where('type', 'page')->sole();
            $this->assertSame('privacy-policy', $privacy->slug);
            $this->assertSame('draft', $privacy->status);
            $this->assertStringContainsString('[', $privacy->content);
            $this->assertSame((string) $privacy->id, (string) setting('privacy_page_id'));
            $this->assertSame('draft', $privacy->translations()->where('locale', 'id')->sole()->status);
            $this->get('/privacy-policy')->assertNotFound();
            $this->get('/id/kebijakan-privasi')->assertNotFound();
            $this->get('/blog')->assertDontSee('Privacy Policy');
            $this->get('/')->assertOk()->assertSee('Latest articles')->assertSee(url('blog'), false);
            $this->get('/id')->assertOk()->assertSee('Artikel terbaru');
        } finally {
            File::deleteDirectory($public);
        }
    }

    public function test_the_seeder_keeps_a_privacy_page_that_is_already_set(): void
    {
        $public = sys_get_temp_dir().'/cms-public-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($public.'/img');
        File::copy(public_path('img/logo-mark.svg'), $public.'/img/logo-mark.svg');
        $this->app->usePublicPath($public);

        try {
            $mine = Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'Mine', 'slug' => 'mine', 'status' => 'published', 'content' => '<p>My policy</p>']);
            \App\Models\Setting::write(['privacy_page_id' => (string) $mine->id]);

            $this->seed(DatabaseSeeder::class);

            $this->assertSame(1, Post::where('type', 'page')->count());
            $this->assertSame((string) $mine->id, (string) setting('privacy_page_id'));
        } finally {
            File::deleteDirectory($public);
        }
    }

    public function test_the_seeder_refuses_without_a_user(): void
    {
        User::query()->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Post::count());
    }
}
